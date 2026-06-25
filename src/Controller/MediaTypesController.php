<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\MediaTypeApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS configuration: Media types')]
class MediaTypesController
{
    public function __construct(
        private readonly MediaTypeApi $mediaTypeApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/media-types', name: 'sfs_cms_api_media_types_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS media types',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw media type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS media types.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->mediaTypeApi->list(
            $this->requestQuery->bool($request, 'includeRawConfig'),
        ));
    }

    #[Route('/media-types/{type}', name: 'sfs_cms_api_media_types_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get configured CMS media type detail',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS media type id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw media type config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS media type.'),
            new OA\Response(response: 404, description: 'Media type not found.'),
        ],
    )]
    public function get(string $type, Request $request): JsonResponse
    {
        $mediaType = $this->mediaTypeApi->get(
            $type,
            $this->requestQuery->bool($request, 'includeRawConfig'),
        );

        if (null === $mediaType) {
            return new JsonResponse(['error' => sprintf('Media type "%s" was not found.', $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($mediaType);
    }
}
