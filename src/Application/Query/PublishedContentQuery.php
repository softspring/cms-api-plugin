<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Serialization\PublishedContentSerializer;

use function count;
use function in_array;
use function trim;

class PublishedContentQuery
{
    public function __construct(
        private readonly ContentProvider $contentProvider,
        private readonly PublishedContentSerializer $contentSerializer,
    ) {}

    public function searchPublished(?string $type, string $query, ?string $site, ?string $locale, int $limit): ?array
    {
        $query = trim($query);
        $contents = [];
        $types = $this->contentProvider->getContentTypes($type);

        if ([] === $types) {
            return null;
        }

        foreach ($types as $resolvedType) {
            foreach ($this->contentProvider->findContents($resolvedType, $site, max($limit * 5, 50), true) as $content) {
                if (!$content->getPublishedVersion() instanceof ContentVersionInterface) {
                    continue;
                }

                if ($locale && !in_array($locale, $content->getLocales() ?? [], true)) {
                    continue;
                }

                if ('' !== $query && !$this->contentProvider->contentMatchesQuery($content, $query)) {
                    continue;
                }

                $contents[] = $this->contentSerializer->summary($content, $resolvedType, $locale);

                if (count($contents) >= $limit) {
                    break 2;
                }
            }
        }

        return [
            'query' => $query,
            'filters' => [
                'contentType' => $type,
                'site' => $site,
                'locale' => $locale,
                'publishedOnly' => true,
            ],
            'count' => count($contents),
            'results' => $contents,
        ];
    }

    public function findPublishedContentById(string $contentId, ?string $type): ?array
    {
        foreach ($this->contentProvider->getContentTypes($type) as $resolvedType) {
            $result = $this->contentProvider->findContent($resolvedType, $contentId);
            if (null === $result) {
                continue;
            }
            [$resolvedType, $content] = $result;
            $version = $content->getPublishedVersion();
            if ($version instanceof ContentVersionInterface) {
                return [$resolvedType, $content, $version];
            }
        }

        return null;
    }
}
