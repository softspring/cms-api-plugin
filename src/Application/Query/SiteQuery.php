<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Serialization\AiInstructionsSerializer;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Model\SiteInterface;
use Softspring\CmsBundle\Serialization\SiteSerializer;

class SiteQuery
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
        private readonly SiteSerializer $siteSerializer,
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
        ] + AiInstructionsSerializer::summarize($site->getConfig() ?? []);

        if ($includeConfig) {
            $data['config'] = $site->getConfig();
        }

        return $data;
    }

    public function listContexts(): array
    {
        $sites = [];
        foreach ($this->cmsConfig->getSites() as $site) {
            if ($site instanceof SiteInterface) {
                $sites[] = $this->siteSerializer->context($site);
            }
        }

        return ['count' => count($sites), 'sites' => $sites];
    }

    public function getContext(string $site): ?array
    {
        $entity = $this->cmsConfig->getSite($site, false);

        return $entity instanceof SiteInterface ? $this->siteSerializer->context($entity, true) : null;
    }
}
