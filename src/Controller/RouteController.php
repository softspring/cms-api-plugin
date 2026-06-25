<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Softspring\CmsApiPlugin\Api\RouteApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Routes')]
class RouteController
{
    public function __construct(
        private readonly RouteApi $routeApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/routes', name: 'sfs_cms_api_routes_list', methods: ['GET'])]
    #[OA\Get(summary: 'List CMS routes')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->routeApi->list(
            $this->requestQuery->nullableString($request, 'site'),
            $this->requestQuery->nullableString($request, 'locale'),
            $this->requestQuery->nullableInt($request, 'type'),
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->int($request, 'limit', 50, 1, 100),
        ));
    }

    #[Route('/routes/{id}', name: 'sfs_cms_api_routes_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get CMS route detail')]
    public function get(string $id, Request $request): JsonResponse
    {
        $route = $this->routeApi->get($id, $this->requestQuery->nullableString($request, 'locale'));
        if (null === $route) {
            return new JsonResponse(['error' => sprintf('Route "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($route);
    }
}
