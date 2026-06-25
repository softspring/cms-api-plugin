<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\ConfigResourceApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS configuration: Layouts')]
class LayoutController
{
    public function __construct(
        private readonly ConfigResourceApi $configResourceApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/layouts', name: 'sfs_cms_api_layouts_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS layouts',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw layout config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS layouts.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->configResourceApi->layouts(
            $this->requestQuery->bool($request, 'includeRawConfig'),
        ));
    }

    #[Route('/layouts/{layout}', name: 'sfs_cms_api_layouts_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get configured CMS layout detail',
        parameters: [
            new OA\Parameter(name: 'layout', description: 'CMS layout id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw layout config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS layout.'),
            new OA\Response(response: 404, description: 'Layout not found.'),
        ],
    )]
    public function get(string $layout, Request $request): JsonResponse
    {
        $layoutContext = $this->configResourceApi->layout(
            $layout,
            $this->requestQuery->bool($request, 'includeRawConfig'),
        );

        if (null === $layoutContext) {
            return new JsonResponse(['error' => sprintf('Layout "%s" was not found.', $layout)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($layoutContext);
    }
}
