<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\Application\Query\AnalyticsQuery;
use Softspring\CmsApiPlugin\Application\Query\ContentQuery;
use Softspring\CmsApiPlugin\Application\Query\SeoStatisticsQuery;
use Softspring\CmsApiPlugin\DependencyInjection\SfsCmsApiExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SfsCmsApiExtensionTest extends TestCase
{
    public function testSharedQueriesDoNotRequireTheAnalyticsBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        new SfsCmsApiExtension()->load([], $container);

        self::assertTrue($container->hasDefinition(ContentQuery::class));
        self::assertFalse($container->hasDefinition(AnalyticsQuery::class));
        self::assertFalse($container->hasDefinition(SeoStatisticsQuery::class));
    }

    public function testAnalyticsQueryIsRegisteredOnlyWithItsProviderBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SfsCmsAnalyticsPlugin' => 'Softspring\CmsAnalyticsPlugin\SfsCmsAnalyticsPlugin']);
        new SfsCmsApiExtension()->load([], $container);

        self::assertTrue($container->hasDefinition(AnalyticsQuery::class));
        self::assertTrue($container->getDefinition(AnalyticsQuery::class)->isAutowired());
    }
    public function testSeoQueryIsRegisteredOnlyWithItsProviderBundle(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SfsCmsSeoPlugin' => 'Softspring\CmsSeoPlugin\SfsCmsSeoPlugin']);
        new SfsCmsApiExtension()->load([], $container);

        self::assertTrue($container->hasDefinition(SeoStatisticsQuery::class));
        self::assertTrue($container->getDefinition(SeoStatisticsQuery::class)->isAutowired());
        self::assertFalse($container->hasDefinition(AnalyticsQuery::class));
    }
}
