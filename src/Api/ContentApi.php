<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Doctrine\ORM\QueryBuilder;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Manager\ContentManagerInterface;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Model\RouteInterface;
use Softspring\CmsBundle\Model\RoutePathInterface;
use Softspring\CmsBundle\Model\SiteInterface;
use Throwable;

use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function max;
use function mb_stripos;
use function str_ends_with;
use function substr;
use function trim;

use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ContentApi
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
        private readonly ContentManagerInterface $contentManager,
    ) {}

    public function list(string $type, string $query = '', ?string $site = null, ?string $locale = null, bool $publishedOnly = false, int $limit = 20): ?array
    {
        $type = $this->resolveContentType($type);
        if (null === $type) {
            return null;
        }

        $query = trim($query);
        $contents = [];

        foreach ($this->findContents($type, $site, max($limit * 5, 50), $publishedOnly) as $content) {
            if ($locale && !in_array($locale, $content->getLocales() ?? [], true)) {
                continue;
            }

            if ('' !== $query && !$this->contentMatchesQuery($content, $query)) {
                continue;
            }

            $contents[] = $this->summary($content, $type, $locale);

            if (count($contents) >= $limit) {
                break;
            }
        }

        return [
            'type' => $type,
            'filters' => [
                'query' => $query,
                'site' => $site,
                'locale' => $locale,
                'publishedOnly' => $publishedOnly,
            ],
            'count' => count($contents),
            'content' => $contents,
        ];
    }

    public function search(?string $type = null, string $query = '', ?string $site = null, ?string $locale = null, bool $publishedOnly = false, int $limit = 20): ?array
    {
        $query = trim($query);
        $contents = [];
        $types = $this->getContentTypes($type);

        if ([] === $types) {
            return null;
        }

        foreach ($types as $resolvedType) {
            foreach ($this->findContents($resolvedType, $site, max($limit * 5, 50), $publishedOnly) as $content) {
                if ($locale && !in_array($locale, $content->getLocales() ?? [], true)) {
                    continue;
                }

                if ('' !== $query && !$this->contentMatchesQuery($content, $query)) {
                    continue;
                }

                $contents[] = $this->summary($content, $resolvedType, $locale);

                if (count($contents) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'query' => $query,
            'filters' => [
                'contentType' => $type,
                'site' => $site,
                'locale' => $locale,
                'publishedOnly' => $publishedOnly,
            ],
            'count' => count($contents),
            'results' => $contents,
        ];
    }

    public function get(string $type, string $contentId, ?string $locale = null, bool $includeData = false): ?array
    {
        $typedContent = $this->findContent($type, $contentId);
        if (null === $typedContent) {
            return null;
        }

        [$resolvedType, $content] = $typedContent;

        return $this->detail($content, $resolvedType, $locale, $includeData);
    }

    public function publishedVersion(string $type, string $contentId, ?string $locale = null, bool $includeData = true): ?array
    {
        $typedContent = $this->findContent($type, $contentId);
        if (null === $typedContent) {
            return null;
        }

        [$resolvedType, $content] = $typedContent;
        $version = $content->getPublishedVersion();
        if (!$version instanceof ContentVersionInterface) {
            return null;
        }

        return [
            'content' => $this->summary($content, $resolvedType, $locale),
            'version' => $this->version($version, true, $includeData),
        ];
    }

    public function publishedDetailById(string $contentId, ?string $type = null, ?string $locale = null, bool $includeData = false): ?array
    {
        $typedContent = $this->findContentById($contentId, $type);
        if (null === $typedContent) {
            return null;
        }

        [$resolvedType, $content] = $typedContent;

        if (!$content->getPublishedVersion() instanceof ContentVersionInterface) {
            return null;
        }

        return $this->detail($content, $resolvedType, $locale, $includeData);
    }

    private function resolveContentType(string $type): ?string
    {
        $type = trim($type);
        if ('' === $type) {
            return null;
        }

        if ($this->cmsConfig->getContent($type, false)) {
            return $type;
        }

        if (str_ends_with($type, 's')) {
            $singularType = substr($type, 0, -1);
            if ($this->cmsConfig->getContent($singularType, false)) {
                return $singularType;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function getContentTypes(?string $type): array
    {
        if (null !== $type && '' !== trim($type)) {
            $resolvedType = $this->resolveContentType($type);

            return null !== $resolvedType ? [$resolvedType] : [];
        }

        return array_keys($this->cmsConfig->getContents());
    }

    /**
     * @return array{0: string, 1: ContentInterface}|null
     */
    private function findContent(string $type, string $contentId): ?array
    {
        $type = $this->resolveContentType($type);
        if (null === $type) {
            return null;
        }

        try {
            $content = $this->contentManager->getRepository($type)->find($contentId);
        } catch (Throwable) {
            return null;
        }

        if (!$content instanceof ContentInterface) {
            return null;
        }

        return [$type, $content];
    }

    /**
     * @return array{0: string, 1: ContentInterface}|null
     */
    private function findContentById(string $contentId, ?string $type): ?array
    {
        foreach ($this->getContentTypes($type) as $resolvedType) {
            try {
                $content = $this->contentManager->getRepository($resolvedType)->find($contentId);
            } catch (Throwable) {
                continue;
            }

            if ($content instanceof ContentInterface) {
                return [$resolvedType, $content];
            }
        }

        return null;
    }

    /**
     * @return list<ContentInterface>
     */
    private function findContents(string $type, ?string $site, int $maxResults, bool $publishedOnly): array
    {
        try {
            $repository = $this->contentManager->getRepository($type);
            $qb = $repository->createQueryBuilder('content')
                ->setMaxResults($maxResults);

            $this->orderContents($qb, $repository->getClassName());

            if ($publishedOnly) {
                $qb->andWhere('content.publishedVersion IS NOT NULL');
            }

            if ($site) {
                $qb
                    ->innerJoin('content.sites', 'site')
                    ->andWhere('site.id = :site')
                    ->setParameter('site', $site);
            }

            return $qb->getQuery()->getResult();
        } catch (Throwable) {
            return [];
        }
    }

    private function orderContents(QueryBuilder $qb, string $className): void
    {
        $metadata = $qb->getEntityManager()->getClassMetadata($className);

        if ($metadata->hasField('name')) {
            $qb->addOrderBy('content.name', 'ASC');
        }

        if ($metadata->hasField('id')) {
            $qb->addOrderBy('content.id', 'ASC');
        }
    }

    private function summary(ContentInterface $content, string $type, ?string $locale = null): array
    {
        $publishedVersion = $content->getPublishedVersion();
        $lastVersion = $content->getLastVersion();

        return [
            'id' => $content->getId(),
            'type' => $type,
            'name' => $content->getName(),
            'defaultLocale' => $content->getDefaultLocale(),
            'locales' => $content->getLocales(),
            'sites' => array_map(static fn(SiteInterface $site): ?string => $site->getId(), $content->getSites()->toArray()),
            'publishedVersion' => $publishedVersion instanceof ContentVersionInterface ? $this->version($publishedVersion) : null,
            'lastVersion' => $lastVersion instanceof ContentVersionInterface ? $this->version($lastVersion) : null,
            'routes' => $this->routes($content, $locale),
        ];
    }

    private function detail(ContentInterface $content, string $type, ?string $locale, bool $includeData): array
    {
        $detail = $this->summary($content, $type, $locale);
        $detail['extraData'] = $content->getExtraData();
        $detail['indexing'] = $content->getIndexing();

        if ($includeData) {
            foreach (['publishedVersion', 'lastVersion'] as $key) {
                if (null === $detail[$key]) {
                    continue;
                }

                $version = 'publishedVersion' === $key ? $content->getPublishedVersion() : $content->getLastVersion();
                if ($version instanceof ContentVersionInterface) {
                    $detail[$key] = $this->version($version, true, true);
                }
            }
        }

        return $detail;
    }

    private function version(ContentVersionInterface $version, bool $includeSeo = false, bool $includeData = false): array
    {
        $data = [
            'id' => $version->getId(),
            'versionNumber' => $version->getVersionNumber(),
            'layout' => $version->getLayout(),
            'origin' => $version->getOrigin(),
            'originDescription' => $version->getOriginDescription(),
            'note' => $version->getNote(),
            'keep' => $version->isKeep(),
            'published' => $version->isPublished(),
            'lastVersion' => $version->isLastVersion(),
            'createdAt' => $version->getCreatedAt()?->format(DATE_ATOM),
            'meta' => $version->getMeta(),
        ];

        if ($includeSeo) {
            $data['seo'] = $version->getSeo();
        }

        if ($includeData) {
            $data['data'] = $version->getData();
        }

        return $data;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routes(ContentInterface $content, ?string $locale = null): array
    {
        return array_map(
            fn(RouteInterface $route): array => [
                'id' => $route->getId(),
                'type' => $route->getType(),
                'paths' => $this->routePaths($route, $locale),
            ],
            $content->getRoutes()->toArray(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function routePaths(RouteInterface $route, ?string $locale = null): array
    {
        $paths = [];

        foreach ($route->getPaths() as $path) {
            if (!$path instanceof RoutePathInterface) {
                continue;
            }

            if ($locale && $path->getLocale() !== $locale) {
                continue;
            }

            $paths[] = [
                'id' => $path->getId(),
                'locale' => $path->getLocale(),
                'path' => $path->getPath(),
                'compiledPath' => $path->getCompiledPath(),
                'cacheTtl' => $path->getCacheTtl(),
                'sites' => array_map(static fn(SiteInterface $site): ?string => $site->getId(), $path->getSites()->toArray()),
            ];
        }

        return $paths;
    }

    private function contentMatchesQuery(ContentInterface $content, string $query): bool
    {
        if (false !== mb_stripos((string) $content->getName(), $query)) {
            return true;
        }

        foreach ([$content->getExtraData(), $content->getIndexing()] as $payload) {
            if ($this->payloadMatchesQuery($payload, $query)) {
                return true;
            }
        }

        foreach ([$content->getPublishedVersion(), $content->getLastVersion()] as $version) {
            if (!$version instanceof ContentVersionInterface) {
                continue;
            }

            foreach ([$version->getSeo(), $version->getData(), $version->getMeta()] as $payload) {
                if ($this->payloadMatchesQuery($payload, $query)) {
                    return true;
                }
            }
        }

        foreach ($content->getSites() as $site) {
            if ($site instanceof SiteInterface && false !== mb_stripos((string) $site->getId(), $query)) {
                return true;
            }
        }

        return false;
    }

    private function payloadMatchesQuery(mixed $payload, string $query): bool
    {
        if (is_string($payload)) {
            return false !== mb_stripos($payload, $query);
        }

        if (is_array($payload)) {
            return false !== mb_stripos((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $query);
        }

        return false;
    }
}
