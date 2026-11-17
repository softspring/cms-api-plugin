<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use Softspring\CmsApiPlugin\Application\Query\ResourceQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ResourceController
{
    public function __construct(private readonly ResourceQuery $resources) {}

    #[Route('/resources/{resource}', name: 'sfs_cms_api_resources_list', methods: ['GET'])]
    #[Route('/resources/{resource}/{id}', name: 'sfs_cms_api_resources_get', methods: ['GET'])]
    public function read(string $resource, Request $request, ?string $id = null): JsonResponse
    {
        return new JsonResponse($this->resources->read($resource, $id, $request->query->all()));
    }
}
