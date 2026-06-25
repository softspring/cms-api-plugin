<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Api;

use Softspring\CmsSectionsPlugin\Manager\SectionManagerInterface;
use Softspring\CmsSectionsPlugin\Model\SectionInterface;
use Softspring\CmsSectionsPlugin\Model\SectionVersionInterface;
use Throwable;

use function count;
use function json_encode;
use function mb_stripos;
use function trim;

use const DATE_ATOM;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class SectionApi
{
    public function __construct(
        private readonly SectionManagerInterface $sectionManager,
    ) {}

    public function list(string $query = '', bool $publishedOnly = false, int $limit = 50): array
    {
        $query = trim($query);
        $sections = [];

        foreach ($this->findSections($limit) as $section) {
            if ($publishedOnly && !$section->getPublishedVersion() instanceof SectionVersionInterface) {
                continue;
            }

            if ('' !== $query && !$this->matchesQuery($section, $query)) {
                continue;
            }

            $sections[] = $this->summary($section);

            if (count($sections) >= $limit) {
                break;
            }
        }

        return [
            'filters' => [
                'query' => $query,
                'publishedOnly' => $publishedOnly,
            ],
            'count' => count($sections),
            'sections' => $sections,
        ];
    }

    public function get(string $id, bool $includeData = false): ?array
    {
        try {
            $section = $this->sectionManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        if (!$section instanceof SectionInterface) {
            return null;
        }

        return $this->detail($section, $includeData);
    }

    public function publishedVersion(string $id, bool $includeData = true): ?array
    {
        try {
            $section = $this->sectionManager->getRepository()->find($id);
        } catch (Throwable) {
            return null;
        }

        if (!$section instanceof SectionInterface) {
            return null;
        }

        $version = $section->getPublishedVersion();
        if (!$version instanceof SectionVersionInterface) {
            return null;
        }

        return [
            'section' => $this->summary($section),
            'version' => $this->version($version, $includeData),
        ];
    }

    /**
     * @return list<SectionInterface>
     */
    private function findSections(int $limit): array
    {
        try {
            return $this->sectionManager->getRepository()->findBy([], ['name' => 'ASC'], $limit);
        } catch (Throwable) {
            return [];
        }
    }

    private function summary(SectionInterface $section): array
    {
        $publishedVersion = $section->getPublishedVersion();
        $lastVersion = $section->getLastVersion();

        return [
            'id' => $section->getId(),
            'name' => $section->getName(),
            'defaultLocale' => $section->getDefaultLocale(),
            'locales' => $section->getLocales(),
            'status' => $section->getStatus(),
            'lastModified' => $section->getLastModified()?->format(DATE_ATOM),
            'publishedVersion' => $publishedVersion instanceof SectionVersionInterface ? $this->version($publishedVersion) : null,
            'lastVersion' => $lastVersion instanceof SectionVersionInterface ? $this->version($lastVersion) : null,
        ];
    }

    private function detail(SectionInterface $section, bool $includeData): array
    {
        $detail = $this->summary($section) + [
            'extraData' => $section->getExtraData(),
            'notes' => $section->getNotes(),
        ];

        if ($includeData) {
            foreach (['publishedVersion', 'lastVersion'] as $key) {
                if (null === $detail[$key]) {
                    continue;
                }

                $version = 'publishedVersion' === $key ? $section->getPublishedVersion() : $section->getLastVersion();
                if ($version instanceof SectionVersionInterface) {
                    $detail[$key] = $this->version($version, true);
                }
            }
        }

        return $detail;
    }

    private function version(SectionVersionInterface $version, bool $includeData = false): array
    {
        $data = [
            'id' => $version->getId(),
            'versionNumber' => $version->getVersionNumber(),
            'origin' => $version->getOrigin(),
            'originDescription' => $version->getOriginDescription(),
            'note' => $version->getNote(),
            'keep' => $version->isKeep(),
            'published' => $version->isPublished(),
            'lastVersion' => $version->isLastVersion(),
            'createdAt' => $version->getCreatedAt()?->format(DATE_ATOM),
            'meta' => $version->getMeta(),
        ];

        if ($includeData) {
            $data['data'] = $version->getData();
        }

        return $data;
    }

    private function matchesQuery(SectionInterface $section, string $query): bool
    {
        if (false !== mb_stripos((string) $section->getName(), $query)) {
            return true;
        }

        if (false !== mb_stripos((string) $section->getNotes(), $query)) {
            return true;
        }

        return false !== mb_stripos((string) json_encode($section->getExtraData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $query);
    }
}
