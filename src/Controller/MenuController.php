<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\MenuApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Menus')]
class MenuController
{
    public function __construct(
        private readonly MenuApi $menuApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/menus', name: 'sfs_cms_api_menus_list', methods: ['GET'])]
    #[OA\Get(summary: 'List CMS menus')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->menuApi->list(
            $this->requestQuery->nullableString($request, 'type'),
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->int($request, 'limit', 50, 1, 100),
        ));
    }

    #[Route('/menus/{id}', name: 'sfs_cms_api_menus_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get CMS menu detail')]
    public function get(string $id): JsonResponse
    {
        $menu = $this->menuApi->get($id);
        if (null === $menu) {
            return new JsonResponse(['error' => sprintf('Menu "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($menu);
    }
}
