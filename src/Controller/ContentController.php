<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\ContentApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Content')]
class ContentController
{
    public function __construct(
        private readonly ContentApi $contentApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/content/{type}', name: 'sfs_cms_api_content_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List CMS content by type',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS content type id. Plural aliases such as pages resolve to the configured singular type when available.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'query', description: 'Free text query matched against content name and data payloads.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'site', description: 'Optional CMS site id.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'locale', description: 'Optional locale filter.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'publishedOnly', description: 'Only return content that has a published version.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'limit', description: 'Maximum number of results. Capped at 100.', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, minimum: 1, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CMS content list.'),
            new OA\Response(response: 404, description: 'Content type not found.'),
        ],
    )]
    public function list(string $type, Request $request): JsonResponse
    {
        $content = $this->contentApi->list(
            $type,
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->nullableString($request, 'site'),
            $this->requestQuery->nullableString($request, 'locale'),
            $this->requestQuery->bool($request, 'publishedOnly'),
            $this->requestQuery->int($request, 'limit', 20, 1, 100),
        );

        if (null === $content) {
            return new JsonResponse(['error' => sprintf('Content type "%s" was not found.', $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($content);
    }

    #[Route('/content/{type}/{id}', name: 'sfs_cms_api_content_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get CMS content detail',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS content type id. Plural aliases such as pages resolve to the configured singular type when available.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'id', description: 'CMS content id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'locale', description: 'Optional locale filter for route summaries.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeData', description: 'Include version data payloads in the response.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CMS content detail.'),
            new OA\Response(response: 404, description: 'Content not found.'),
        ],
    )]
    public function get(string $type, string $id, Request $request): JsonResponse
    {
        $content = $this->contentApi->get(
            $type,
            $id,
            $this->requestQuery->nullableString($request, 'locale'),
            $this->requestQuery->bool($request, 'includeData'),
        );

        if (null === $content) {
            return new JsonResponse(['error' => sprintf('Content "%s" of type "%s" was not found.', $id, $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($content);
    }

    #[Route('/content/{type}/{id}/versions/published', name: 'sfs_cms_api_content_versions_published_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get published CMS content version',
        parameters: [
            new OA\Parameter(name: 'type', description: 'CMS content type id. Plural aliases such as pages resolve to the configured singular type when available.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'id', description: 'CMS content id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'locale', description: 'Optional locale filter for route summaries.', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeData', description: 'Include the published version data payload.', in: 'query', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Published CMS content version.'),
            new OA\Response(response: 404, description: 'Published version not found.'),
        ],
    )]
    public function publishedVersion(string $type, string $id, Request $request): JsonResponse
    {
        $version = $this->contentApi->publishedVersion(
            $type,
            $id,
            $this->requestQuery->nullableString($request, 'locale'),
            $this->requestQuery->bool($request, 'includeData', true),
        );

        if (null === $version) {
            return new JsonResponse(['error' => sprintf('Published version for content "%s" of type "%s" was not found.', $id, $type)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($version);
    }
}
