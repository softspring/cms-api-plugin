<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\DependencyInjection;

use Softspring\CmsApiPlugin\Application\Query\AnalyticsQuery;
use Softspring\CmsApiPlugin\Application\Query\SeoStatisticsQuery;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class SfsCmsApiExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config/services'));
        $loader->load('services.yaml');
        $container->removeDefinition(AnalyticsQuery::class);
        $container->removeDefinition(SeoStatisticsQuery::class);

        $bundles = $container->hasParameter('kernel.bundles') ? $container->getParameter('kernel.bundles') : [];
        if (in_array('Softspring\\CmsAnalyticsPlugin\\SfsCmsAnalyticsPlugin', $bundles, true)) {
            $loader->load('analytics.yaml');
        }
        if (in_array('Softspring\\CmsSeoPlugin\\SfsCmsSeoPlugin', $bundles, true)) {
            $loader->load('seo.yaml');
        }
    }
}
