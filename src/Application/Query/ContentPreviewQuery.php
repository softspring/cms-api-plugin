<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Compiler\ContentVersionCompiler;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Render\Isolated\IsolatedRequest;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ContentPreviewQuery
{
    public function __construct(
        private readonly ContentProvider $contents,
        private readonly ContentVersionCompiler $compiler,
    ) {}

    public function preview(string $type, string $id, ?string $versionId = null, ?string $locale = null, ?string $siteId = null): array
    {
        $content = $this->contents->findContent($type, $id)[1] ?? throw new NotFoundHttpException('Content was not found.');
        $version = $content->getLastVersion();
        if (null !== $versionId) {
            $version = $content->getVersions()->filter(static fn(ContentVersionInterface $item): bool => (string) $item->getId() === $versionId)->first();
        }
        if (!$version instanceof ContentVersionInterface) {
            throw new NotFoundHttpException('Version was not found for this content.');
        }
        $locale ??= $content->getDefaultLocale();
        if (!in_array($locale, $content->getLocales() ?? [], true)) {
            throw new BadRequestHttpException('Locale is not configured for this content.');
        }
        $site = $content->getSites()->filter(static fn($item): bool => $item->isEnabled() && (null === $siteId || $item->getId() === $siteId))->first();
        if (!$site || !in_array($locale, $site->getConfig()['locales'] ?? [], true)) {
            throw new BadRequestHttpException('An enabled content site supporting this locale is required.');
        }
        $request = IsolatedRequest::createIsolatedForContentRoute($content, $locale, $site, true);
        // A preview must not attach compiled data to the version or publish it.
        $compiled = $this->compiler->compileRequest($version, $request, null, false);

        return ['ok' => !$compiled->hasErrors(), 'contentId' => (string) $content->getId(),
            'versionId' => (string) $version->getId(), 'site' => $site->getId(), 'locale' => $locale,
            'published' => $version->isPublished(), 'html' => $compiled->getDataPart('content') ?? '',
            'errors' => $compiled->getDataPart('errors') ?? [], 'containerErrors' => $compiled->getDataPart('containers_errors') ?? []];
    }
}
