<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests\Application\Query;

use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\Application\Query\ElementInstructionsQuery;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Model\SiteInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;

class ElementInstructionsQueryTest extends TestCase
{
    public function testCatalogContainsOnlyUsageTextAndKeepsElementScope(): void
    {
        $config = $this->createMock(CmsConfig::class);
        $config->method('getContents')->willReturn(['page' => ['meta' => ['ai_instructions' => 'Evergreen content.'], 'secret' => 'not guidance']]);
        $config->method('getLayouts')->willReturn(['landing' => ['meta' => ['ai_instructions' => 'Landing layout.', 'containers' => ['main' => ['ai_instructions' => 'Main body.']]], 'containers' => ['main' => []]]]);
        $config->expects(self::once())->method('getModules')->with(true)->willReturn(['paragraph' => ['meta' => ['ai_instructions' => 'One paragraph.']], 'empty' => []]);
        $config->method('getMenus')->willReturn(['main' => ['meta' => ['ai_instructions' => 'Primary navigation.']]]);
        $config->method('getBlocks')->willReturn(['promo' => ['meta' => ['ai_instructions' => 'Reusable promotion.']], 'disabled' => ['enabled' => false, 'meta' => ['ai_instructions' => 'Do not expose.']]]);
        $site = $this->createStub(SiteInterface::class);
        $site->method('getId')->willReturn('web');
        $site->method('getConfig')->willReturn(['meta' => ['ai_instructions' => 'Corporate site.']]);
        $config->method('getSites')->willReturn([$site]);
        $media = $this->createStub(MediaTypesCollection::class);
        $media->method('getTypes')->willReturn(['background' => ['meta' => ['ai_instructions' => 'Wide image.']]]);
        $catalog = new ElementInstructionsQuery($config, $media)->all();
        self::assertSame('Evergreen content.', $catalog['contents']['page']);
        self::assertSame('Landing layout.', $catalog['layouts']['landing']);
        self::assertSame('Main body.', $catalog['containers']['layouts']['landing']['main']);
        self::assertSame(['paragraph' => 'One paragraph.'], $catalog['modules']);
        self::assertSame('Primary navigation.', $catalog['menus']['main']);
        self::assertSame(['promo' => 'Reusable promotion.'], $catalog['blocks']);
        self::assertSame('Corporate site.', $catalog['sites']['web']);
        self::assertSame('Wide image.', $catalog['mediaTypes']['background']);
        self::assertStringNotContainsString('not guidance', json_encode($catalog));
    }
}
