<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin;

use Softspring\CmsBundle\Plugin\SfsCmsPlugin;

use function dirname;

class SfsCmsApiPlugin extends SfsCmsPlugin
{
    public static function getAlias(): string
    {
        return 'sfs_cms_api';
    }

    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
