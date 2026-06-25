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

#[OA\Tag(name: 'CMS configuration: Content types')]
class ContentTypesController
{
    public function __construct(
        private readonly ConfigResourceApi $configResourceApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/content-types', name: 'sfs_cms_api_content_types_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS content types',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw content type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS content types.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->configResourceApi->contentTypes(
            $this->requestQuery->bool($request, 'includeRawConfig'),
        ));
    }

    #[Route('/content-types/{type}', name: 'sfs_cms_api_content_types_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get configured CMS content type detail',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS content type id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw content type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS content type.'),
            new OA\Response(response: 404, description: 'Content type not found.'),
        ],
    )]
    public function get(string $type, Request $request): JsonResponse
    {
        $contentType = $this->configResourceApi->contentType(
            $type,
            $this->requestQuery->bool($request, 'includeRawConfig'),
        );

        if (null === $contentType) {
            return new JsonResponse(['error' => sprintf('Content type "%s" was not found.', $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($contentType);
    }
}
