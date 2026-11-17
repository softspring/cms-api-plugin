<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin;

use Softspring\CmsBundle\Plugin\SfsCmsPlugin;
use Softspring\CmsApiPlugin\DependencyInjection\Compiler\OperationRegistryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function dirname;

class SfsCmsApiPlugin extends SfsCmsPlugin
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new OperationRegistryPass());
    }

    public static function getAlias(): string
    {
        return 'sfs_cms_api';
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
