<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\DependencyInjection\Compiler;

use Softspring\CmsApiPlugin\Application\Operation\OperationRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OperationRegistryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(OperationRegistry::class)) {
            return;
        }
        $controllers = [
            'contents' => 'sfs_cms.admin.content.controller',
            'content_versions' => 'sfs_cms.admin.content_version.controller',
            'routes' => 'sfs_cms.admin.route.controller',
            'menus' => 'sfs_cms.admin.menu.controller',
            'blocks' => 'sfs_cms.admin.block.controller',
            'sections' => 'sfs_cms.admin.section.controller',
            'section_versions' => 'sfs_cms.admin.section_version.controller',
            'media' => 'sfs_media.admin.media.controller',
        ];
        $resources = [];
        foreach ($controllers as $resource => $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }
            $definition = $container->getDefinition($id);
            $resources[$resource] = [
                'manager' => $definition->getArgument('$manager'),
                'configs' => $definition->getArgument('$configs'),
            ];
        }
        $container->getDefinition(OperationRegistry::class)->setArgument('$resources', $resources);
    }
}
