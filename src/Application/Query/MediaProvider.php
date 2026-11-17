<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Throwable;

use function mb_stripos;

class MediaProvider
{
    public function __construct(
        private readonly MediaManagerInterface $mediaManager,
    ) {}

    public function findMedia(int $limit): array
    {
        try {
            return $this->mediaManager->getRepository()->findBy([], ['createdAt' => 'DESC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    public function findMediaById(string $id): ?MediaInterface
    {
        try {
            $media = $this->mediaManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        return $media instanceof MediaInterface ? $media : null;
    }

    public function matchesQuery(MediaInterface $media, string $query): bool
    {
        return false !== mb_stripos((string) $media->getName(), $query)
            || false !== mb_stripos((string) $media->getDescription(), $query)
            || false !== mb_stripos((string) $media->getId(), $query);
    }
}
