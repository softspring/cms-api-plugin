<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Type\MediaTypesCollection;

use function array_keys;
use function array_map;
use function array_values;
use function count;
use function is_array;

class MediaTypeApi
{
    public function __construct(
        private readonly MediaTypesCollection $mediaTypesCollection,
    ) {}

    public function list(bool $includeRawConfig = false): array
    {
        $mediaTypes = [];

        foreach ($this->mediaTypesCollection->getTypes() as $type => $config) {
            $mediaTypes[$type] = $this->describe($type, $config, $includeRawConfig);
        }

        return [
            'count' => count($mediaTypes),
            'mediaTypes' => array_values($mediaTypes),
        ];
    }

    public function get(string $type, bool $includeRawConfig = false): ?array
    {
        try {
            $config = $this->mediaTypesCollection->getType($type);
        } catch (InvalidTypeException) {
            return null;
        }

        return $this->describe($type, $config, $includeRawConfig);
    }

    private function describe(string $type, array $config, bool $includeRawConfig): array
    {
        $description = [
            'type' => $type,
            'label' => $config['label'] ?? $type,
            'mediaType' => $config['type'] ?? null,
            'private' => (bool) ($config['private'] ?? false),
            'uploadRequirements' => $config['upload_requirements'] ?? [],
            'versionKeys' => array_keys($config['versions'] ?? []),
            'pictureKeys' => array_keys($config['pictures'] ?? []),
            'videoSetKeys' => array_keys($config['video_sets'] ?? []),
        ];

        if ($includeRawConfig) {
            $description['config'] = $this->sanitize($config);
        }

        return $description;
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
