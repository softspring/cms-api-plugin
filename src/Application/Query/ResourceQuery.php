<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsApiPlugin\Application\Operation\OperationRegistry;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ResourceQuery
{
    public function __construct(
        private readonly ContentQuery $contents,
        private readonly RouteQuery $routes,
        private readonly MenuQuery $menus,
        private readonly BlockQuery $blocks,
        private readonly SectionQuery $sections,
        private readonly MediaQuery $media,
        private readonly SiteQuery $sites,
        private readonly ContentPreviewQuery $previews,
        private readonly OperationRegistry $registry,
        private readonly AuthorizationCheckerInterface $security,
    ) {}

    public function read(string $resource, ?string $id = null, array $filters = []): array
    {
        if (!$this->security->isGranted('CMS_API_READ', ['resource' => $resource])) {
            throw new AccessDeniedHttpException('Missing permission CMS_API_READ.');
        }
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 20)));
        $query = (string) ($filters['query'] ?? '');
        $type = $filters['type'] ?? null;
        $locale = $filters['locale'] ?? null;
        $site = $filters['site'] ?? null;
        $result = match ($resource) {
            'content_preview' => $this->previews->preview(
                $type ?? throw new BadRequestHttpException('Content type is required.'),
                $id ?? throw new BadRequestHttpException('Content identifier is required.'),
                $filters['version'] ?? null,
                $locale,
                $site,
            ),
            'contents' => $id ? $this->contents->get($type ?? throw new BadRequestHttpException('Content type is required.'), $id, $locale, true)
                : $this->contents->search($type, $query, $site, $locale, false, $limit),
            'routes' => $id ? $this->routes->get($id, $locale) : $this->routes->list($site, $locale, $type ? (int) $type : null, $query, $limit),
            'menus' => $id ? $this->menus->get($id) : $this->menus->list($type, $query, $limit),
            'blocks' => $id ? $this->blocks->get($id) : $this->blocks->list($type, $query, $limit),
            'sections' => $id ? $this->sections->get($id, true) : $this->sections->list($query, false, $limit),
            'media' => $id ? $this->media->get($id) : $this->media->list($type, $query, $limit),
            'sites' => $id ? $this->sites->getContext($id) : $this->sites->list(),
            'content_versions', 'section_versions' => $this->versions($resource, $id, $filters, $limit),
            default => throw new NotFoundHttpException('CMS resource is not available.'),
        };

        return $result ?? throw new NotFoundHttpException('CMS element was not found.');
    }

    private function versions(string $resource, ?string $id, array $filters, int $limit): array
    {
        $parent = 'content_versions' === $resource ? 'content' : 'section';
        if (empty($filters[$parent])) {
            throw new BadRequestHttpException('A parent ' . $parent . ' identifier is required.');
        }
        $manager = $this->registry->resource($resource)['manager'];
        $criteria = [$parent => $filters[$parent]];
        if ($id) {
            $criteria['id'] = $id;
        }
        $items = $manager->getRepository()->findBy($criteria, ['versionNumber' => 'DESC'], $limit);
        if ($id && !$items) {
            throw new NotFoundHttpException('Version was not found for this parent.');
        }
        $versions = [];
        foreach ($items as $version) {
            $row = ['id' => (string) $version->getId(), 'versionNumber' => $version->getVersionNumber(),
                'data' => $version->getData(), 'note' => $version->getNote(), 'keep' => $version->isKeep(),
                'published' => $version->isPublished()];
            if ($version instanceof ContentVersionInterface) {
                $row['seo'] = $version->getSeo();
                $row['layout'] = $version->getLayout();
            }
            $versions[] = $row;
        }

        return ['versions' => $versions, 'count' => count($versions)];
    }
}
