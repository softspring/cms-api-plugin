<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Model\RouteInterface;
use Softspring\CmsBundle\Model\SiteInterface;

use function array_map;
use function count;
use function in_array;
use function max;
use function trim;

use const DATE_ATOM;

class ContentQuery
{
    public function __construct(
        private readonly ContentProvider $contentProvider,
    ) {}

    public function list(string $type, string $query = '', ?string $site = null, ?string $locale = null, bool $publishedOnly = false, int $limit = 20): ?array
    {
        $type = $this->contentProvider->resolveContentType($type);
        if (null === $type) {
            return null;
        }

        $query = trim($query);
        $contents = [];

        foreach ($this->contentProvider->findContents($type, $site, max($limit * 5, 50), $publishedOnly) as $content) {
            if ($locale && !in_array($locale, $content->getLocales() ?? [], true)) {
                continue;
            }

            if ('' !== $query && !$this->contentProvider->contentMatchesQuery($content, $query)) {
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
        $types = $this->contentProvider->getContentTypes($type);

        if ([] === $types) {
            return null;
        }

        foreach ($types as $resolvedType) {
            foreach ($this->contentProvider->findContents($resolvedType, $site, max($limit * 5, 50), $publishedOnly) as $content) {
                if ($locale && !in_array($locale, $content->getLocales() ?? [], true)) {
                    continue;
                }

                if ('' !== $query && !$this->contentProvider->contentMatchesQuery($content, $query)) {
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
        $typedContent = $this->contentProvider->findContent($type, $contentId);
        if (null === $typedContent) {
            return null;
        }

        [$resolvedType, $content] = $typedContent;

        return $this->detail($content, $resolvedType, $locale, $includeData);
    }

    public function publishedVersion(string $type, string $contentId, ?string $locale = null, bool $includeData = true): ?array
    {
        $typedContent = $this->contentProvider->findContent($type, $contentId);
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
        $typedContent = $this->contentProvider->findContentById($contentId, $type);
        if (null === $typedContent) {
            return null;
        }

        [$resolvedType, $content] = $typedContent;

        if (!$content->getPublishedVersion() instanceof ContentVersionInterface) {
            return null;
        }

        return $this->detail($content, $resolvedType, $locale, $includeData);
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
}
