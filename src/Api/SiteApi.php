<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Model\SiteInterface;

class SiteApi
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
    ) {}

    public function list(bool $includeRawConfig = false): array
    {
        $sites = [];

        foreach ($this->cmsConfig->getSites() as $site) {
            if (!$site instanceof SiteInterface) {
                continue;
            }

            $sites[] = $this->summarize($site, $includeRawConfig);
        }

        return [
            'count' => count($sites),
            'sites' => $sites,
        ];
    }

    public function context(string $site, bool $includeConfig = false): ?array
    {
        $siteEntity = $this->cmsConfig->getSite($site, false);
        if (!$siteEntity instanceof SiteInterface) {
            return null;
        }

        return $this->summarize($siteEntity, $includeConfig) + [
            'metadata' => $siteEntity->getMetadata(),
        ];
    }

    private function summarize(SiteInterface $site, bool $includeConfig = false): array
    {
        $data = [
            'id' => $site->getId(),
            'canonical' => [
                'scheme' => $site->getCanonicalScheme(),
                'host' => $site->getCanonicalHost(),
                'port' => $site->getCanonicalPort(),
            ],
        ];

        if ($includeConfig) {
            $data['config'] = $site->getConfig();
        }

        return $data;
    }
}
