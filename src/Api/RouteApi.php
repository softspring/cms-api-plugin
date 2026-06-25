<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsBundle\Manager\ContentManagerInterface;
use Softspring\CmsBundle\Manager\RouteManagerInterface;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\RouteInterface;
use Softspring\CmsBundle\Model\RoutePathInterface;
use Softspring\CmsBundle\Model\SiteInterface;
use Throwable;

use function array_map;
use function count;
use function mb_stripos;
use function trim;

class RouteApi
{
    public function __construct(
        private readonly RouteManagerInterface $routeManager,
        private readonly ContentManagerInterface $contentManager,
    ) {}

    public function list(?string $site = null, ?string $locale = null, ?int $type = null, string $query = '', int $limit = 50): array
    {
        $query = trim($query);
        $routes = [];

        foreach ($this->findRoutes($limit) as $route) {
            if (null !== $type && $route->getType() !== $type) {
                continue;
            }

            if ($site && !$route->hasSite($site)) {
                continue;
            }

            if ($locale && !$this->hasLocale($route, $locale)) {
                continue;
            }

            if ('' !== $query && !$this->matchesQuery($route, $query)) {
                continue;
            }

            $routes[] = $this->summary($route, $locale);

            if (count($routes) >= $limit) {
                break;
            }
        }

        return [
            'filters' => [
                'site' => $site,
                'locale' => $locale,
                'type' => $type,
                'query' => $query,
            ],
            'count' => count($routes),
            'routes' => $routes,
        ];
    }

    public function get(string $id, ?string $locale = null): ?array
    {
        try {
            $route = $this->routeManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        if (!$route instanceof RouteInterface) {
            return null;
        }

        return $this->detail($route, $locale);
    }

    /**
     * @return list<RouteInterface>
     */
    private function findRoutes(int $limit): array
    {
        try {
            return $this->routeManager->getRepository()->findBy([], ['id' => 'ASC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function summary(RouteInterface $route, ?string $locale = null): array
    {
        $content = $route->getContent();

        return [
            'id' => $route->getId(),
            'type' => $route->getType(),
            'sites' => array_map(static fn(SiteInterface $site): ?string => $site->getId(), $route->getSites()->toArray()),
            'parent' => $route->getParent()?->getId(),
            'content' => $content instanceof ContentInterface ? [
                'id' => $content->getId(),
                'type' => $this->resolveContentType($content),
                'name' => $content->getName(),
            ] : null,
            'paths' => $this->paths($route, $locale),
        ];
    }

    private function detail(RouteInterface $route, ?string $locale = null): array
    {
        return $this->summary($route, $locale) + [
            'redirectUrl' => $route->getRedirectUrl(),
            'redirectType' => $route->getRedirectType(),
            'symfonyRoute' => $route->getSymfonyRoute(),
            'linkAttrs' => $route->getLinkAttrs(),
            'children' => array_map(
                static fn(RouteInterface $child): mixed => $child->getId(),
                $route->getChildren()?->toArray() ?? [],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paths(RouteInterface $route, ?string $locale = null): array
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

    private function hasLocale(RouteInterface $route, string $locale): bool
    {
        foreach ($route->getPaths() as $path) {
            if ($path instanceof RoutePathInterface && $path->getLocale() === $locale) {
                return true;
            }
        }

        return false;
    }

    private function matchesQuery(RouteInterface $route, string $query): bool
    {
        if (false !== mb_stripos((string) $route->getId(), $query)) {
            return true;
        }

        foreach ($route->getPaths() as $path) {
            if (!$path instanceof RoutePathInterface) {
                continue;
            }

            if (false !== mb_stripos((string) $path->getPath(), $query) || false !== mb_stripos((string) $path->getCompiledPath(), $query)) {
                return true;
            }
        }

        return false;
    }

    private function resolveContentType(ContentInterface $content): ?string
    {
        try {
            return $this->contentManager->getType($content);
        } catch (Throwable) {
            return null;
        }
    }
}
