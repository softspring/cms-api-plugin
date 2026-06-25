<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Throwable;

use function array_map;
use function count;
use function max;
use function mb_stripos;
use function trim;

use const DATE_ATOM;

class MediaApi
{
    public function __construct(
        private readonly MediaManagerInterface $mediaManager,
    ) {}

    public function list(?string $type = null, string $query = '', int $limit = 50, ?string $mediaType = null, bool $includePrivate = true): array
    {
        $query = trim($query);
        $mediaItems = [];

        foreach ($this->findMedia(max($limit * 5, 50)) as $media) {
            if ($mediaType && $media->getMediaType() !== $mediaType) {
                continue;
            }

            if ($type && $media->getType() !== $type) {
                continue;
            }

            if (!$includePrivate && $media->getPrivate()) {
                continue;
            }

            if ('' !== $query && !$this->matchesQuery($media, $query)) {
                continue;
            }

            $mediaItems[] = $this->summary($media);

            if (count($mediaItems) >= $limit) {
                break;
            }
        }

        return [
            'filters' => [
                'type' => $type,
                'mediaType' => $mediaType,
                'query' => $query,
                'includePrivate' => $includePrivate,
            ],
            'count' => count($mediaItems),
            'media' => $mediaItems,
        ];
    }

    public function get(string $id): ?array
    {
        $media = $this->findMediaById($id);
        if (!$media instanceof MediaInterface) {
            return null;
        }

        return $this->summary($media) + [
            'altTexts' => $media->getAltTexts(),
            'metadata' => $media->getMetadata(),
            'versions' => array_map(
                fn(MediaVersionInterface $version): array => $this->version($version, true),
                $media->getVersions()->toArray(),
            ),
        ];
    }

    public function preview(string $id): ?array
    {
        $media = $this->findMediaById($id);
        if (!$media instanceof MediaInterface) {
            return null;
        }

        return [
            'id' => $media->getId(),
            'name' => $media->getName(),
            'thumbnail' => $this->thumbnail($media),
        ];
    }

    /**
     * @return list<MediaInterface>
     */
    private function findMedia(int $limit): array
    {
        try {
            return $this->mediaManager->getRepository()->findBy([], ['createdAt' => 'DESC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function findMediaById(string $id): ?MediaInterface
    {
        try {
            $media = $this->mediaManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        return $media instanceof MediaInterface ? $media : null;
    }

    private function summary(MediaInterface $media): array
    {
        return [
            'id' => $media->getId(),
            'type' => $media->getType(),
            'mediaType' => $media->getMediaType(),
            'name' => $media->getName(),
            'description' => $media->getDescription(),
            'private' => $media->getPrivate(),
            'createdAt' => $media->getCreatedAt()?->format(DATE_ATOM),
            'thumbnail' => $this->thumbnail($media),
        ];
    }

    private function thumbnail(MediaInterface $media): ?array
    {
        $thumbnail = $media->getVersion('_thumbnail');

        return $thumbnail instanceof MediaVersionInterface ? $this->version($thumbnail) : null;
    }

    private function version(MediaVersionInterface $version, bool $includeInternalUrl = false): array
    {
        $data = [
            'version' => $version->getVersion(),
            'publicUrl' => $version->getPublicUrl(),
            'width' => $version->getWidth(),
            'height' => $version->getHeight(),
            'fileSize' => $version->getFileSize(),
            'mimeType' => $version->getFileMimeType(),
        ];

        if ($includeInternalUrl) {
            $data['url'] = $version->getUrl();
            $data['uploadedAt'] = $version->getUploadedAt()?->format(DATE_ATOM);
            $data['generatedAt'] = $version->getGeneratedAt()?->format(DATE_ATOM);
        }

        return $data;
    }

    private function matchesQuery(MediaInterface $media, string $query): bool
    {
        return false !== mb_stripos((string) $media->getName(), $query)
            || false !== mb_stripos((string) $media->getDescription(), $query)
            || false !== mb_stripos((string) $media->getId(), $query);
    }
}
