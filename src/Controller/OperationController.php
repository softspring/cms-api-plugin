<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Controller;

use Softspring\CmsApiPlugin\Application\Operation\OperationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class OperationController
{
    public function __construct(private readonly OperationService $operations) {}

    #[Route('/operations', name: 'sfs_cms_api_operations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse($this->operations->catalog());
    }

    #[Route('/operations/{resource}/{action}/schema', name: 'sfs_cms_api_operations_schema', methods: ['POST'])]
    public function schema(string $resource, string $action, Request $request): JsonResponse
    {
        $input = $request->toArray();

        return new JsonResponse($this->operations->describe($resource, $action, $input['context'] ?? []));
    }

    #[Route('/operations/{resource}/{action}', name: 'sfs_cms_api_operations_execute', methods: ['POST'])]
    public function execute(string $resource, string $action, Request $request): JsonResponse
    {
        $input = $request->toArray();
        $result = $this->operations->execute($resource, $action, $input['context'] ?? [], $input['data'] ?? [], true === ($input['confirmed'] ?? false));

        return new JsonResponse($result, ($result['ok'] ?? false) ? 200 : 422);
    }
}
