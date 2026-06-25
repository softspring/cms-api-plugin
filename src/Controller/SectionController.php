<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Softspring\CmsApiPlugin\Api\SectionApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS instances: Sections')]
class SectionController
{
    public function __construct(
        private readonly SectionApi $sectionApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/sections', name: 'sfs_cms_api_sections_list', methods: ['GET'])]
    #[OA\Get(summary: 'List CMS sections')]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->sectionApi->list(
            $this->requestQuery->string($request, 'query'),
            $this->requestQuery->bool($request, 'publishedOnly'),
            $this->requestQuery->int($request, 'limit', 50, 1, 100),
        ));
    }

    #[Route('/sections/{id}', name: 'sfs_cms_api_sections_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get CMS section detail')]
    public function get(string $id, Request $request): JsonResponse
    {
        $section = $this->sectionApi->get($id, $this->requestQuery->bool($request, 'includeData'));
        if (null === $section) {
            return new JsonResponse(['error' => sprintf('Section "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($section);
    }

    #[Route('/sections/{id}/versions/published', name: 'sfs_cms_api_sections_versions_published_get', methods: ['GET'])]
    #[OA\Get(summary: 'Get published CMS section version')]
    public function publishedVersion(string $id, Request $request): JsonResponse
    {
        $version = $this->sectionApi->publishedVersion($id, $this->requestQuery->bool($request, 'includeData', true));
        if (null === $version) {
            return new JsonResponse(['error' => sprintf('Published version for section "%s" was not found.', $id)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($version);
    }
}
