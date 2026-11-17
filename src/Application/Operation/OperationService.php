<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Operation;

use Softspring\CmsApiPlugin\Application\Query\ElementInstructionsQuery;
use Softspring\Component\CrudlController\Config\Configuration;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Translator\TranslatableContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ChoiceList\Factory\ChoiceListFactoryInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Symfony\Contracts\Service\ResetInterface;

final class OperationService
{
    public function __construct(
        private readonly OperationRegistry $registry,
        private readonly EventDispatcherInterface $events,
        private readonly Environment $twig,
        private readonly FormFactoryInterface $forms,
        private readonly AuthorizationCheckerInterface $security,
        private readonly RouterInterface $router,
        private readonly RequestStack $requests,
        private readonly FormSchema $schema,
        private readonly ElementInstructionsQuery $elementInstructions,
        private readonly CmsConfig $cmsConfig,
        private readonly TranslatableContext $translations,
        private readonly FormRegistryInterface $formRegistry,
        #[Autowire(service: 'form.choice_list_factory')]
        private readonly ChoiceListFactoryInterface $choiceLists,
    ) {}

    public function catalog(): array
    {
        return ['operations' => $this->registry->all()];
    }

    public function describe(string $resource, string $action, array $context = []): array
    {
        return $this->run($resource, $action, $context, [], false, false);
    }

    public function execute(string $resource, string $action, array $context = [], array $data = [], bool $confirmed = false): array
    {
        return $this->run($resource, $action, $context, $data, true, $confirmed);
    }

    private function run(string $resource, string $action, array $context, array $data, bool $execute, bool $confirmed): array
    {
        $operation = $this->registry->get($resource, $action);
        // Authentication is host-defined; each transport uses the same authorization boundary.
        $attribute = 'CMS_API_' . strtoupper(substr($operation['scope'], 4));
        if (!$this->security->isGranted($attribute, $operation)) {
            throw new AccessDeniedHttpException('Missing permission ' . $attribute . '.');
        }
        if ($execute && 'cms:write' !== $operation['scope'] && !$confirmed) {
            throw new BadRequestHttpException('This operation requires confirmed=true.');
        }
        $this->resetRequestState();
        $entry = $this->registry->resource($resource);
        $manager = $entry['manager'];
        $em = $manager->getEntityManager();
        $connection = $em->getConnection();
        $request = $this->request($resource, $context, $data);
        $translationLocales = $this->translations->getLocales();
        $translationDefault = $this->translations->getDefaultLocale();
        $transactionLevel = $connection->getTransactionNestingLevel();
        $connection->beginTransaction();
        $temporaryFiles = [];
        $this->requests->push($request);
        $committed = false;
        try {
            $kind = $operation['kind'];
            $configs = $entry['configs'];
            $configs[$action]['entity_attribute'] = $operation['entityAttribute'];
            $method = 'apply' === $kind ? 'actionAction' : $kind . 'Action';
            $helper = new OperationFormHelper($manager, $this->events, $this->twig, $this->security, $this->router, $this->forms);
            $helper->setConfig(Configuration::$method($action, $configs));
            $helper->setRequest($request);
            $this->checkResponse($helper->dispatchInitialize());
            if ('create' === $kind) {
                if (null === $helper->dispatchCreateEntityEvent()) {
                    $helper->createEntity();
                }
            } else {
                if (!$helper->dispatchLoadEntityEvent()) {
                    $helper->findEntity();
                }
                if ($helper->notFound()) {
                    throw new NotFoundHttpException('CMS element was not found in the requested context.');
                }
            }
            $helper->checkIsGranted();
            if ('create' !== $kind) {
                $this->checkResponse($helper->dispatchFoundEvent());
            }
            $form = null;
            if ($operation['hasForm']) {
                $form = $helper->createForm($helper->dispatchFormPrepare());
                $helper->dispatchFormInit();
            }
            if (!$execute) {
                return ['operation' => $operation, 'context' => $context, 'form' => $form ? $this->schema->describe($form) : null, 'aiInstructions' => $this->elementInstructions->all()];
            }
            if ($form) {
                $form->submit($this->files($data, $temporaryFiles), true);
                if (!$form->isValid()) {
                    $errors = [];
                    foreach ($form->getErrors(true) as $error) {
                        $path = [];
                        for ($field = $error->getOrigin(); $field && $field->getParent(); $field = $field->getParent()) {
                            array_unshift($path, $field->getName());
                        }
                        $errors[] = ['field' => implode('.', $path), 'message' => $error->getMessage()];
                    }

                    return ['ok' => false, 'errors' => $errors];
                }
                $this->checkResponse($helper->dispatchFormValid());
            } elseif ([] !== $data) {
                throw new BadRequestHttpException('This operation does not accept form data.');
            }
            if (!$helper->dispatchApplyEvent()) {
                if ('apply' === $kind) {
                    throw new ConflictHttpException('CMS action did not apply.');
                }
                if ('delete' === $kind) {
                    $manager->deleteEntity($helper->getEntity());
                } else {
                    $manager->saveEntity($helper->getEntity());
                }
            }
            $entity = $helper->getEntity();
            $result = ['ok' => true, 'resource' => $resource, 'action' => $action,
                'id' => method_exists($entity, 'getId') ? (string) $entity->getId() : null];
            $connection->commit();
            $committed = true;

            return $result;
        } finally {
            if (!$committed) {
                if ($connection->getTransactionNestingLevel() > $transactionLevel) {
                    $connection->rollBack();
                }
                // Form/event preparation may have changed managed objects even when no SQL was flushed.
                $em->clear();
                $this->resetRequestState();
            }
            $this->translations->setLocales($translationLocales);
            $this->translations->setDefaultLocale($translationDefault);
            $this->requests->pop();
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function resetRequestState(): void
    {
        foreach ([$this->cmsConfig, $this->formRegistry->getType(EntityType::class)->getInnerType(), $this->choiceLists] as $service) {
            if ($service instanceof ResetInterface) {
                $service->reset();
            }
        }
    }

    private function request(string $resource, array $context, array $data): Request
    {
        $allowed = ['id', 'type', 'content', 'section', 'version', 'prevVersion', 'locale', 'layout'];
        if (array_diff(array_keys($context), $allowed)) {
            throw new BadRequestHttpException('Unknown CMS operation context field.');
        }
        foreach ($context as $value) {
            if (!is_string($value) && !is_int($value)) {
                throw new BadRequestHttpException('Context values must be identifiers.');
            }
        }
        $request = Request::create('/cms-api-operation', 'GET');
        $request->setRequestFormat('json');
        $request->attributes->set('_locale', $context['locale'] ?? 'en');
        $entry = $this->registry->resource($resource);
        $request->attributes->set($entry['configs']['entity_attribute'], $context['id'] ?? null);
        foreach (['content', 'section', 'version', 'prevVersion'] as $key) {
            if (isset($context[$key])) {
                $request->attributes->set($key, $context[$key]);
            }
        }
        if (isset($context['type'])) {
            foreach (['_content_type', 'blockType', 'menuType', 'type'] as $key) {
                $request->attributes->set($key, $context['type']);
            }
        }
        // Layout selection is consumed before CMS version forms are built.
        if (isset($data['layout']) || isset($context['layout'])) {
            $request->request->set('version_create_form', ['layout' => $data['layout'] ?? $context['layout']]);
        }
        if (isset($context['version'])) {
            $request->query->set('version', $context['version']);
        }
        $current = $this->requests->getCurrentRequest();
        if ($current?->hasSession()) {
            $request->setSession($current->getSession());
        }

        return $request;
    }

    private function checkResponse(?Response $response): void
    {
        if ($response) {
            throw new ConflictHttpException('The CMS workflow rejected this operation or its context.');
        }
    }

    private function files(array $data, array &$temporaryFiles): array
    {
        foreach ($data as &$value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['_file'])) {
                $file = $value['_file'];
                $encoded = $file['base64'] ?? '';
                if (!is_string($encoded) || strlen($encoded) > 24 * 1024 * 1024) {
                    throw new BadRequestHttpException('File payload exceeds the maximum size.');
                }
                $decoded = base64_decode($encoded, true);
                if (false === $decoded || '' === $decoded) {
                    throw new BadRequestHttpException('File payload must contain valid non-empty base64.');
                }
                $path = tempnam(sys_get_temp_dir(), 'cms-api-');
                $temporaryFiles[] = $path;
                file_put_contents($path, $decoded);
                $value = new UploadedFile($path, basename($file['name'] ?? 'upload'), null, null, true);
            } else {
                $value = $this->files($value, $temporaryFiles);
            }
        }

        return $data;
    }
}
