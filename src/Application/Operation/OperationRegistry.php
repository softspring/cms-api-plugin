<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Operation;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OperationRegistry
{
    private const ACTIONS = ['create', 'update', 'delete', 'duplicate', 'routes', 'unpublish',
        'version_create', 'version_seo', 'version_info', 'version_lock', 'version_recompile',
        'version_publish', 'version_cleanup', 'version_clear_compiled', 'version_delete'];

    public function __construct(private readonly array $resources = []) {}

    public function all(): array
    {
        $operations = [];
        foreach ($this->resources as $resource => $entry) {
            foreach (self::ACTIONS as $action) {
                if (isset($entry['configs'][$action])) {
                    $operations[] = $this->get($resource, $action);
                }
            }
        }

        return $operations;
    }

    public function get(string $resource, string $action): array
    {
        $entry = $this->resources[$resource] ?? null;
        if (!$entry || !in_array($action, self::ACTIONS, true) || !isset($entry['configs'][$action])) {
            throw new NotFoundHttpException('CMS operation is not available.');
        }
        $config = $entry['configs'][$action];
        $kind = !empty($config['create_entity_event_name']) || 'create' === $action ? 'create'
            : (str_ends_with($action, 'delete') ? 'delete'
            : (!empty($config['form_prepare_event_name']) || !empty($config['form']) ? 'update' : 'apply'));
        $scope = str_contains($action, 'delete') || str_contains($action, 'cleanup') ? 'cms:delete'
            : (in_array($action, ['unpublish', 'version_publish', 'version_recompile', 'version_clear_compiled'], true) ? 'cms:publish' : 'cms:write');

        return ['resource' => $resource, 'action' => $action, 'kind' => $kind, 'scope' => $scope,
            'entityAttribute' => $entry['configs']['entity_attribute'], 'hasForm' => 'apply' !== $kind];
    }

    public function resource(string $resource): array
    {
        return $this->resources[$resource] ?? throw new NotFoundHttpException('CMS resource is not available.');
    }
}
