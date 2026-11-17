<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsAnalyticsPlugin\Analytics\PageStatisticsQuery;
use Softspring\CmsAnalyticsPlugin\Analytics\StatisticsProviderChain;
use Softspring\CmsApiPlugin\Application\Query\ContentProvider;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Model\RouteInterface;
use Softspring\CmsBundle\Model\RoutePathInterface;
use Softspring\CmsBundle\Model\SiteInterface;
use Softspring\CmsBundle\Serialization\SiteSerializer;
use Throwable;

use function array_key_exists;
use function array_slice;
use function count;
use function fnmatch;
use function in_array;
use function is_string;
use function parse_url;
use function sprintf;
use function strtolower;
use function trim;

use const PHP_URL_PATH;

class AnalyticsQuery
{
    private const METRICS = [
        'visitors',
        'visits',
        'pageviews',
        'views_per_visit',
        'bounce_rate',
        'time_on_page',
    ];

    public function __construct(
        private readonly CmsConfig $cmsConfig,
        private readonly ContentProvider $contentProvider,
        private readonly SiteSerializer $siteSerializer,
        private readonly StatisticsProviderChain $statisticsProviderChain,
    ) {}

    public function getSiteAnalytics(
        string $site,
        string $dateRange = '30d',
        ?string $path = null,
    ): array {
        try {
            $siteEntity = $this->cmsConfig->getSite($site, false);
            if (!$siteEntity instanceof SiteInterface) {
                return ['error' => sprintf('Site "%s" was not found.', $site)];
            }

            $path = null !== $path && '' !== trim($path) ? $this->normalizePath($path) : null;
            $providerChain = $this->getStatisticsProviderChain();
            $provider = $providerChain->getProvider($siteEntity, $path);

            if (null === $provider) {
                return [
                    'installed' => false,
                    'error' => 'No CMS analytics statistics provider is available for this site.',
                    'availableProviders' => $providerChain->getProviderNames(),
                ];
            }

            $configurationData = $provider->resolveConfiguration($siteEntity, $path)->toArray();

            if (!($configurationData['usable'] ?? false)) {
                return [
                    'installed' => true,
                    'site' => $this->serializeSite($siteEntity),
                    'provider' => $configurationData['provider'] ?? $provider->getName(),
                    'dateRange' => $dateRange,
                    'path' => $path,
                    'configuration' => $configurationData,
                    'metrics' => $this->emptyMetrics(),
                ];
            }

            return [
                'installed' => true,
                'site' => $this->serializeSite($siteEntity),
                'provider' => $configurationData['provider'] ?? $provider->getName(),
                'dateRange' => $dateRange,
                'path' => $path,
                'configuration' => $configurationData,
                'metrics' => $provider->getSiteMetrics($siteEntity, $dateRange, $path),
            ];
        } catch (Throwable $e) {
            return $this->toolError($e);
        }
    }

    public function queryPages(
        string $site,
        string $dateRange = '30d',
        ?string $path = null,
        ?string $pathPattern = null,
        ?string $contentType = null,
        ?string $locale = null,
        string $metric = 'pageviews',
        string $orderDirection = 'desc',
        int $page = 1,
        int $limit = 10,
        bool $includeContent = true,
    ): array {
        try {
            $siteEntity = $this->cmsConfig->getSite($site, false);
            if (!$siteEntity instanceof SiteInterface) {
                return ['error' => sprintf('Site "%s" was not found.', $site)];
            }

            $metric = $this->normalizeRankingMetric($metric);
            $orderDirection = $this->normalizeOrderDirection($orderDirection);
            $page = max(1, $page);
            $limit = $this->normalizeLimit($limit);
            $path = null !== $path && '' !== trim($path) ? $this->normalizePath($path) : null;
            $pathPattern = null !== $pathPattern && '' !== trim($pathPattern) ? $this->normalizePathPattern($pathPattern) : null;
            $contentType = null !== $contentType && '' !== trim($contentType) ? $contentType : null;
            $providerChain = $this->getStatisticsProviderChain();
            $provider = $providerChain->getProvider($siteEntity, $path);

            if (null === $provider) {
                return [
                    'installed' => false,
                    'error' => 'No CMS analytics statistics provider is available for this site.',
                    'availableProviders' => $providerChain->getProviderNames(),
                ];
            }

            $configurationData = $provider->resolveConfiguration($siteEntity, $path)->toArray();

            if (!($configurationData['usable'] ?? false)) {
                return [
                    'installed' => true,
                    'site' => $this->serializeSite($siteEntity),
                    'provider' => $configurationData['provider'] ?? $provider->getName(),
                    'dateRange' => $dateRange,
                    'filters' => [
                        'path' => $path,
                        'pathPattern' => $pathPattern,
                        'contentType' => $contentType,
                        'locale' => $locale,
                        'metric' => $metric,
                        'orderDirection' => $orderDirection,
                        'page' => $page,
                        'limit' => $limit,
                        'includeContent' => $includeContent,
                    ],
                    'configuration' => $configurationData,
                    'count' => 0,
                    'results' => [],
                ];
            }

            $contentByPath = $includeContent ? $this->buildContentPathIndex($contentType, $site, $locale) : [];
            $requiresLocalFiltering = null !== $pathPattern || $includeContent;
            $queryLimit = $requiresLocalFiltering ? 500 : $limit;
            $queryPage = $requiresLocalFiltering ? 1 : $page;
            $pageRows = $provider->queryPages($siteEntity, new PageStatisticsQuery($dateRange, $metric, $orderDirection, $queryPage, $queryLimit, $path));
            $results = [];

            foreach ($pageRows as $pageRow) {
                $rowPath = $this->normalizePath($pageRow['path']);
                if (null !== $pathPattern && !fnmatch($pathPattern, $rowPath)) {
                    continue;
                }

                $row = [
                    'path' => $rowPath,
                    'url' => $this->buildSiteUrl($siteEntity, $rowPath),
                    'analytics' => $pageRow['metrics'],
                    'rankMetric' => $metric,
                    'rankValue' => $pageRow['metrics'][$metric] ?? 0,
                ];

                if ($includeContent) {
                    $row['content'] = $this->findContentByAnalyticsPath($rowPath, $contentByPath);
                    if (null !== $contentType && null === $row['content']) {
                        continue;
                    }
                }

                $results[] = $row;
            }

            $totalMatchedRows = count($results);
            if ($requiresLocalFiltering) {
                $results = array_slice($results, ($page - 1) * $limit, $limit);
            }

            foreach ($results as $index => &$result) {
                $result['rank'] = (($page - 1) * $limit) + $index + 1;
            }
            unset($result);

            return [
                'installed' => true,
                'site' => $this->serializeSite($siteEntity),
                'provider' => $configurationData['provider'] ?? $provider->getName(),
                'dateRange' => $dateRange,
                'filters' => [
                    'path' => $path,
                    'pathPattern' => $pathPattern,
                    'contentType' => $contentType,
                    'locale' => $locale,
                    'metric' => $metric,
                    'orderDirection' => $orderDirection,
                    'page' => $page,
                    'limit' => $limit,
                    'includeContent' => $includeContent,
                ],
                'configuration' => $configurationData,
                'availableContentPaths' => count($contentByPath),
                'queriedAnalyticsPages' => count($pageRows),
                'totalMatchedRows' => $totalMatchedRows,
                'count' => count($results),
                'results' => $results,
            ];
        } catch (Throwable $e) {
            return $this->toolError($e);
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildContentPathIndex(?string $contentType, string $site, ?string $locale): array
    {
        $contentByPath = [];

        foreach ($this->getContentTypes($contentType) as $type) {
            foreach ($this->findPublishedContents($type, $site, 2000) as $content) {
                $version = $content->getPublishedVersion();
                if (!$version instanceof ContentVersionInterface) {
                    continue;
                }

                foreach ($content->getRoutes() as $route) {
                    if (!$route instanceof RouteInterface) {
                        continue;
                    }

                    foreach ($route->getPaths() as $path) {
                        if ($locale && $path->getLocale() !== $locale) {
                            continue;
                        }

                        if (!$this->pathBelongsToSite($path, $site)) {
                            continue;
                        }

                        $compiledPath = $path->getCompiledPath() ?: $path->getPath();
                        if (!is_string($compiledPath) || '' === trim($compiledPath)) {
                            continue;
                        }

                        $contentByPath[$this->normalizePath($compiledPath)] = [
                            'contentId' => $content->getId(),
                            'contentType' => $type,
                            'name' => $content->getName(),
                            'defaultLocale' => $content->getDefaultLocale(),
                            'locales' => $content->getLocales(),
                            'routeId' => $route->getId(),
                            'routeType' => $route->getType(),
                            'locale' => $path->getLocale(),
                            'publishedVersion' => [
                                'id' => $version->getId(),
                                'versionNumber' => $version->getVersionNumber(),
                                'layout' => $version->getLayout(),
                            ],
                        ];
                    }
                }
            }
        }

        return $contentByPath;
    }

    /**
     * @param array<string, array<string, mixed>> $contentByPath
     *
     * @return array<string, mixed>|null
     */
    private function findContentByAnalyticsPath(string $analyticsPath, array $contentByPath): ?array
    {
        if (array_key_exists($analyticsPath, $contentByPath)) {
            return $contentByPath[$analyticsPath];
        }

        $matchedContent = null;
        $matchedLength = 0;

        foreach ($contentByPath as $contentPath => $contentRow) {
            if ('/' === $contentPath || !str_ends_with($analyticsPath, $contentPath)) {
                continue;
            }

            $contentPathLength = strlen($contentPath);
            if ($contentPathLength <= $matchedLength) {
                continue;
            }

            $matchedContent = $contentRow;
            $matchedLength = $contentPathLength;
        }

        return $matchedContent;
    }

    /**
     * @return list<ContentInterface>
     */
    private function findPublishedContents(string $contentType, ?string $site, int $maxResults): array
    {
        return $this->contentProvider->findContents($contentType, $site, $maxResults, true);
    }

    /**
     * @return list<string>
     */
    private function getContentTypes(?string $contentType): array
    {
        return $this->contentProvider->getContentTypes($contentType);
    }

    private function normalizePath(string $path): string
    {
        $parsedPath = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsedPath) ? $parsedPath : $path;
        $path = '/' . ltrim(trim($path), '/');
        $path = rtrim($path, '/');

        return '' === $path ? '/' : $path;
    }

    private function normalizeRankingMetric(string $metric): string
    {
        return in_array($metric, ['pageviews', 'visitors', 'visits'], true) ? $metric : 'pageviews';
    }

    private function normalizeOrderDirection(string $orderDirection): string
    {
        return 'asc' === strtolower(trim($orderDirection)) ? 'asc' : 'desc';
    }

    private function normalizePathPattern(string $pathPattern): string
    {
        $pathPattern = '/' . ltrim(trim($pathPattern), '/');
        $pathPattern = rtrim($pathPattern, '/');

        return '' === $pathPattern ? '/' : $pathPattern;
    }

    private function normalizeLimit(int $limit): int
    {
        return max(1, min(50, $limit));
    }

    private function pathBelongsToSite(RoutePathInterface $path, string $site): bool
    {
        $sites = $path->getSites()->toArray();

        if ([] === $sites) {
            return true;
        }

        foreach ($sites as $pathSite) {
            if ($pathSite instanceof SiteInterface && $pathSite->getId() === $site) {
                return true;
            }
        }

        return false;
    }

    private function buildSiteUrl(SiteInterface $site, string $path): ?string
    {
        $host = $site->getCanonicalHost();
        if (!$host) {
            return null;
        }

        $scheme = $site->getCanonicalScheme() ?: 'https';
        $port = $site->getCanonicalPort();
        $portPart = $port && !in_array($port, [80, 443], true) ? ':' . $port : '';

        return sprintf('%s://%s%s%s', $scheme, $host, $portPart, $this->normalizePath($path));
    }

    private function getStatisticsProviderChain(): StatisticsProviderChain
    {
        return $this->statisticsProviderChain;
    }

    private function emptyMetrics(): array
    {
        return array_fill_keys(self::METRICS, 0);
    }

    private function serializeSite(SiteInterface $site): array
    {
        return $this->siteSerializer->summarize($site);
    }

    private function toolError(Throwable $e): array
    {
        return [
            'error' => $e->getMessage(),
            'type' => $e::class,
        ];
    }
}
