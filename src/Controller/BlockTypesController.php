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

#[OA\Tag(name: 'CMS configuration: Block types')]
class BlockTypesController
{
    public function __construct(
        private readonly ConfigResourceApi $configResourceApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/block-types', name: 'sfs_cms_api_block_types_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS block types',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw block type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS block types.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->configResourceApi->blocks(
            $this->requestQuery->bool($request, 'includeRawConfig'),
        ));
    }

    #[Route('/block-types/{type}', name: 'sfs_cms_api_block_types_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get configured CMS block type detail',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS block type id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw block type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS block type.'),
            new OA\Response(response: 404, description: 'Block type not found.'),
        ],
    )]
    public function get(string $type, Request $request): JsonResponse
    {
        $blockType = $this->configResourceApi->block(
            $type,
            $this->requestQuery->bool($request, 'includeRawConfig'),
        );

        if (null === $blockType) {
            return new JsonResponse(['error' => sprintf('Block type "%s" was not found.', $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($blockType);
    }
}
