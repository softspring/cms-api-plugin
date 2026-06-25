<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests;

use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\SfsCmsApiPlugin;

class SfsCmsApiPluginTest extends TestCase
{
    public function testProvidesCmsPluginAlias(): void
    {
        self::assertSame('sfs_cms_api', SfsCmsApiPlugin::getAlias());
    }

    public function testReturnsPackagePath(): void
    {
        self::assertDirectoryExists(new SfsCmsApiPlugin()->getPath());
    }
}
