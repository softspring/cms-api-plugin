<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Serialization\AiInstructionsSerializer;
use Softspring\MediaBundle\Type\MediaTypesCollection;

final class ElementInstructionsQuery
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
        private readonly MediaTypesCollection $mediaTypes,
    ) {}

    public function all(): array
    {
        $catalog = [];
        $definitions = [
            'contents' => $this->cmsConfig->getContents(),
            'layouts' => array_filter($this->cmsConfig->getLayouts(), static fn(array $config): bool => $config['enabled'] ?? true),
            'modules' => $this->cmsConfig->getModules(true),
            'menus' => $this->cmsConfig->getMenus(),
            'blocks' => array_filter($this->cmsConfig->getBlocks(), static fn(array $config): bool => $config['enabled'] ?? true),
            'mediaTypes' => $this->mediaTypes->getTypes(),
        ];
        foreach ($this->cmsConfig->getSites() as $site) {
            $definitions['sites'][(string) $site->getId()] = $site->getConfig() ?? [];
        }
        foreach ($definitions as $section => $elements) {
            foreach ($elements as $id => $config) {
                $summary = AiInstructionsSerializer::summarize($config);
                if (isset($summary['aiInstructions'])) {
                    $catalog[$section][$id] = $summary['aiInstructions'];
                }
                foreach ($config['containers'] ?? [] as $container => $containerConfig) {
                    $summary = AiInstructionsSerializer::summarize(['meta' => $config['meta']['containers'][$container] ?? []]);
                    if (isset($summary['aiInstructions'])) {
                        $catalog['containers'][$section][$id][$container] = $summary['aiInstructions'];
                    }
                }
            }
        }

        return $catalog;
    }
}
