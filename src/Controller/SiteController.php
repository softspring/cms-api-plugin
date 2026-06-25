<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use OpenApi\Attributes as OA;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Softspring\CmsApiPlugin\Api\SiteApi;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'CMS configuration: Sites')]
class SiteController
{
    public function __construct(
        private readonly SiteApi $siteApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/sites', name: 'sfs_cms_api_sites_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS sites',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw site config and metadata. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS sites.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->siteApi->list(
            $this->requestQuery->bool($request, 'includeRawConfig'),
        ));
    }

    #[Route('/sites/{site}', name: 'sfs_cms_api_sites_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get CMS site context',
        parameters: [
            new OA\Parameter(name: 'site', description: 'CMS site id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeConfig', description: 'Include raw site config.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'CMS site context.'),
            new OA\Response(response: 404, description: 'Site not found.'),
        ],
    )]
    public function get(string $site, Request $request): JsonResponse
    {
        $context = $this->siteApi->context($site, $this->requestQuery->bool($request, 'includeConfig'));
        if (null === $context) {
            return new JsonResponse(['error' => sprintf('Site "%s" was not found.', $site)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($context);
    }
}
