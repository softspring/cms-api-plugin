<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Serialization\MediaImageSerializer;
use Softspring\CmsBundle\Serialization\MediaTypeRequirementsSerializer;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Throwable;

use function array_keys;
use function array_values;
use function count;
use function max;
use function min;
use function trim;

class MediaImageQuery
{
    public function __construct(
        private readonly MediaTypesCollection $mediaTypesCollection,
        private readonly MediaProvider $mediaProvider,
        private readonly MediaTypeRequirementsSerializer $requirementsSerializer,
        private readonly MediaImageSerializer $mediaImageSerializer,
    ) {}

    public function listImageTypes(
        bool $includePrivate = false,
    ): array {
        $types = [];

        foreach ($this->mediaTypesCollection->getTypes() as $type => $typeConfig) {
            $type = (string) $type;

            if ('image' !== ($typeConfig['type'] ?? null)) {
                continue;
            }

            if (!$includePrivate && ($typeConfig['private'] ?? false)) {
                continue;
            }

            $types[$type] = $this->describeMediaType($type, $typeConfig);
        }

        ksort($types);

        return [
            'count' => count($types),
            'types' => array_values($types),
        ];
    }

    public function searchImages(
        ?string $query = null,
        ?string $type = null,
        bool $includePrivate = false,
        int $limit = 20,
    ): array {
        $limit = max(1, min($limit, 50));
        $query = trim((string) $query);
        $type = trim((string) $type);

        if ('' !== $type) {
            $typeContext = $this->findMediaType($type);
            if (null === $typeContext) {
                return ['error' => sprintf('Media type "%s" was not found.', $type)];
            }

            if ('image' !== ($typeContext['mediaType'] ?? null)) {
                return ['error' => sprintf('Media type "%s" is not an image type.', $type)];
            }
        }

        $images = [];

        foreach ($this->mediaProvider->findMedia(max($limit * 5, 50)) as $media) {
            if (!$media->isImage()) {
                continue;
            }

            if ('' !== $type && $media->getType() !== $type) {
                continue;
            }

            if (!$includePrivate && $media->getPrivate()) {
                continue;
            }

            if ('' !== $query && !$this->mediaProvider->matchesQuery($media, $query)) {
                continue;
            }

            $images[] = ['id' => $media->getId()] + $this->mediaImageSerializer->preview($media);

            if (count($images) >= $limit) {
                break;
            }
        }

        return [
            'query' => '' !== $query ? $query : null,
            'type' => '' !== $type ? $type : null,
            'includePrivate' => $includePrivate,
            'limit' => $limit,
            'count' => count($images),
            'images' => $images,
        ];
    }

    public function getImageContext(
        string $mediaId,
    ): array {
        $media = $this->mediaProvider->findMediaById($mediaId);

        if (!$media instanceof MediaInterface) {
            return ['error' => sprintf('Media "%s" was not found.', $mediaId)];
        }

        if (!$media->isImage()) {
            return ['error' => sprintf('Media "%s" is not an image.', $mediaId)];
        }

        return $this->mediaImageSerializer->context($media);
    }

    private function findMediaType(string $type): ?array
    {
        try {
            return $this->describeMediaType($type, $this->mediaTypesCollection->getType($type));
        } catch (Throwable) {
            return null;
        }
    }

    private function describeMediaType(string $type, array $typeConfig): array
    {
        return $this->requirementsSerializer->describe($type, $typeConfig) + [
            'private' => (bool) ($typeConfig['private'] ?? false),
            'versionKeys' => array_keys($typeConfig['versions'] ?? []),
            'pictureKeys' => array_keys($typeConfig['pictures'] ?? []),
            'videoSetKeys' => array_keys($typeConfig['video_sets'] ?? []),
        ];
    }
}
