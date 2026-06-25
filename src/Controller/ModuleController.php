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

#[OA\Tag(name: 'CMS configuration: Modules')]
class ModuleController
{
    public function __construct(
        private readonly ConfigResourceApi $configResourceApi,
        private readonly RequestQuery $requestQuery,
    ) {}

    #[Route('/modules', name: 'sfs_cms_api_modules_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List configured CMS modules',
        parameters: [
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw module config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'includeDisabledModules', description: 'Include disabled modules.', in: 'query', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS modules.'),
        ],
    )]
    public function list(Request $request): JsonResponse
    {
        return new JsonResponse($this->configResourceApi->modules(
            $this->requestQuery->bool($request, 'includeRawConfig'),
            $this->requestQuery->bool($request, 'includeDisabledModules', true),
        ));
    }

    #[Route('/modules/{module}', name: 'sfs_cms_api_modules_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get configured CMS module detail',
        parameters: [
            new OA\Parameter(name: 'module', description: 'CMS module id.', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'includeRawConfig', description: 'Include raw module config. Sensitive values are redacted.', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
            new OA\Parameter(name: 'includeDisabledModules', description: 'Include disabled modules.', in: 'query', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Configured CMS module.'),
            new OA\Response(response: 404, description: 'Module not found.'),
        ],
    )]
    public function get(string $module, Request $request): JsonResponse
    {
        $moduleContext = $this->configResourceApi->module(
            $module,
            $this->requestQuery->bool($request, 'includeRawConfig'),
            $this->requestQuery->bool($request, 'includeDisabledModules', true),
        );

        if (null === $moduleContext) {
            return new JsonResponse(['error' => sprintf('Module "%s" was not found.', $module)], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($moduleContext);
    }
}
