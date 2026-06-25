<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Model\SiteInterface;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_array;

class ConfigResourceApi
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
    ) {}

    public function counts(): array
    {
        return [
            'sites' => count($this->cmsConfig->getSites()),
            'contentTypes' => count($this->cmsConfig->getContents()),
            'blocks' => count($this->cmsConfig->getBlocks()),
            'menus' => count($this->cmsConfig->getMenus()),
            'layouts' => count($this->cmsConfig->getLayouts()),
            'modules' => count($this->cmsConfig->getModules(false)),
            'plugins' => count($this->cmsConfig->getRegisteredPlugins()),
        ];
    }

    public function sites(bool $includeRawConfig = false): array
    {
        $sites = array_map(
            fn(SiteInterface $site): array => $this->siteSummary($site, $includeRawConfig),
            $this->cmsConfig->getSites(),
        );

        return [
            'count' => count($sites),
            'sites' => array_values($sites),
        ];
    }

    public function site(string $site, bool $includeRawConfig = false): ?array
    {
        $siteEntity = $this->cmsConfig->getSite($site, false);

        return $siteEntity instanceof SiteInterface ? $this->siteSummary($siteEntity, $includeRawConfig) : null;
    }

    public function contentTypes(bool $includeRawConfig = false): array
    {
        $contentTypes = $this->configuredResources($this->cmsConfig->getContents(), $includeRawConfig);

        return [
            'count' => count($contentTypes),
            'contentTypes' => array_values($contentTypes),
        ];
    }

    public function contentType(string $type, bool $includeRawConfig = false): ?array
    {
        $contentTypes = $this->configuredResources($this->cmsConfig->getContents(), $includeRawConfig);

        return $contentTypes[$type] ?? null;
    }

    public function blocks(bool $includeRawConfig = false): array
    {
        $blocks = $this->configuredResources($this->cmsConfig->getBlocks(), $includeRawConfig);

        return [
            'count' => count($blocks),
            'blocks' => array_values($blocks),
        ];
    }

    public function block(string $block, bool $includeRawConfig = false): ?array
    {
        $blocks = $this->configuredResources($this->cmsConfig->getBlocks(), $includeRawConfig);

        return $blocks[$block] ?? null;
    }

    public function menus(bool $includeRawConfig = false): array
    {
        $menus = $this->configuredResources($this->cmsConfig->getMenus(), $includeRawConfig);

        return [
            'count' => count($menus),
            'menus' => array_values($menus),
        ];
    }

    public function menu(string $menu, bool $includeRawConfig = false): ?array
    {
        $menus = $this->configuredResources($this->cmsConfig->getMenus(), $includeRawConfig);

        return $menus[$menu] ?? null;
    }

    public function layouts(bool $includeRawConfig = false): array
    {
        $layouts = $this->configuredResources($this->cmsConfig->getLayouts(), $includeRawConfig);

        return [
            'count' => count($layouts),
            'layouts' => array_values($layouts),
        ];
    }

    public function layout(string $layout, bool $includeRawConfig = false): ?array
    {
        $layouts = $this->configuredResources($this->cmsConfig->getLayouts(), $includeRawConfig);

        return $layouts[$layout] ?? null;
    }

    public function modules(bool $includeRawConfig = false, bool $includeDisabledModules = true): array
    {
        $modules = $this->configuredResources($this->cmsConfig->getModules(!$includeDisabledModules), $includeRawConfig);

        return [
            'count' => count($modules),
            'modules' => array_values($modules),
        ];
    }

    public function module(string $module, bool $includeRawConfig = false, bool $includeDisabledModules = true): ?array
    {
        $modules = $this->configuredResources($this->cmsConfig->getModules(!$includeDisabledModules), $includeRawConfig);

        if (!array_key_exists($module, $modules)) {
            return null;
        }

        return $modules[$module];
    }

    public function plugins(): mixed
    {
        return $this->cmsConfig->getRegisteredPlugins();
    }

    private function siteSummary(SiteInterface $site, bool $includeRawConfig): array
    {
        $summary = [
            'id' => $site->getId(),
            'canonical' => [
                'scheme' => $site->getCanonicalScheme(),
                'host' => $site->getCanonicalHost(),
                'port' => $site->getCanonicalPort(),
            ],
            'metadata' => $site->getMetadata(),
        ];

        if ($includeRawConfig) {
            $summary['config'] = $this->sanitize($site->getConfig() ?? []);
        }

        return $summary;
    }

    /**
     * @param array<string, array<string, mixed>> $resources
     *
     * @return array<string, array<string, mixed>>
     */
    private function configuredResources(array $resources, bool $includeRawConfig): array
    {
        $configured = [];

        foreach ($resources as $id => $config) {
            $configured[$id] = [
                'id' => $id,
                'label' => $config['label'] ?? $config['name'] ?? $id,
                'keys' => array_keys($config),
            ];

            if (array_key_exists('enabled', $config)) {
                $configured[$id]['enabled'] = (bool) $config['enabled'];
            }

            if ($includeRawConfig) {
                $configured[$id]['config'] = $this->sanitize($config);
            }
        }

        return $configured;
    }

    private function sanitize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        return array_map(
            fn(mixed $item, string|int $key): mixed => preg_match('/(secret|token|password|key|credential)/i', (string) $key)
                ? 'redacted'
                : $this->sanitize($item),
            $value,
            array_keys($value),
        );
    }
}
