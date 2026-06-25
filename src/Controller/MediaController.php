<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\MediaApi;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Media')]
class MediaController
{
    public function __construct(
        private readonly MediaApi $mediaApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/media', name: 'sfs_cms_api_media_list', methods: ['GET'])]
    #[OA\Get(summary: 'List CMS media')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->mediaApi->list(
            $this->requestQuery->nullableString($request, 'type'),
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->int($request, 'limit', 50, 1, 100),
        ));
    }

    #[Route('/media/{id}', name: 'sfs_cms_api_media_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get CMS media detail')]
    public function get(string $id): JsonResponse
    {
        $media = $this->mediaApi->get($id);
        if (null === $media) {
            return new JsonResponse(['error' => sprintf('Media "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($media);
    }
}
