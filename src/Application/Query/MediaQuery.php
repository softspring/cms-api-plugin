<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;

use function array_map;
use function count;
use function max;
use function trim;

use const DATE_ATOM;

class MediaQuery
{
    public function __construct(private readonly MediaProvider $mediaProvider) {}

    public function list(?string $type = null, string $query = '', int $limit = 50, ?string $mediaType = null, bool $includePrivate = true): array
    {
        $query = trim($query);
        $mediaItems = [];

        foreach ($this->mediaProvider->findMedia(max($limit * 5, 50)) as $media) {
            if ($mediaType && $media->getMediaType() !== $mediaType) {
                continue;
            }

            if ($type && $media->getType() !== $type) {
                continue;
            }

            if (!$includePrivate && $media->getPrivate()) {
                continue;
            }

            if ('' !== $query && !$this->mediaProvider->matchesQuery($media, $query)) {
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
        $media = $this->mediaProvider->findMediaById($id);
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
        $media = $this->mediaProvider->findMediaById($id);
        if (!$media instanceof MediaInterface) {
            return null;
        }

        return [
            'id' => $media->getId(),
            'name' => $media->getName(),
            'thumbnail' => $this->thumbnail($media),
        ];
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
}
