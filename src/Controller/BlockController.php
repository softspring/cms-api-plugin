<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\BlockApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Blocks')]
class BlockController
{
    public function __construct(
        private readonly BlockApi $blockApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/blocks', name: 'sfs_cms_api_blocks_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List CMS blocks',
        parameters: [
            new OA\Parameter(name: 'type', description: 'Filter by CMS block type id.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'query', description: 'Search in block name and data.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', description: 'Maximum number of blocks to return.', in: 'query', schema: new OA\Schema(type: 'integer', default: 50, minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CMS blocks.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->blockApi->list(
            $this->requestQuery->nullableString($request, 'type'),
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->int($request, 'limit', 50, 1, 100),
        ));
    }

    #[Route('/blocks/{id}', name: 'sfs_cms_api_blocks_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get CMS block detail',
        parameters: [
            new OA\Parameter(name: 'id', description: 'CMS block id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CMS block.'),
            new OA\Response(response: 404, description: 'Block not found.'),
        ],
    )]
    public function get(string $id): JsonResponse
    {
        $block = $this->blockApi->get($id);
        if (null === $block) {
            return new JsonResponse(['error' => sprintf('Block "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($block);
    }
}
