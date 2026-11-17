<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use Doctrine\ORM\QueryBuilder;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Manager\ContentManagerInterface;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Throwable;

use function is_array;
use function is_string;
use function json_encode;
use function mb_stripos;
use function str_ends_with;
use function substr;
use function trim;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

class ContentProvider
{
    public function __construct(
        private readonly CmsConfig $cmsConfig,
        private readonly ContentManagerInterface $contentManager,
    ) {}

    public function resolveContentType(string $type): ?string
    {
        $type = trim($type);
        if ('' === $type) {
            return null;
        }

        if ($this->cmsConfig->getContent($type, false)) {
            return $type;
        }

        if (str_ends_with($type, 's')) {
            $singularType = substr($type, 0, -1);
            if ($this->cmsConfig->getContent($singularType, false)) {
                return $singularType;
            }
        }

        return null;
    }

    public function getContentTypes(?string $type): array
    {
        if (null !== $type && '' !== trim($type)) {
            $resolvedType = $this->resolveContentType($type);

            return null !== $resolvedType ? [$resolvedType] : [];
        }

        return array_keys($this->cmsConfig->getContents());
    }

    public function findContent(string $type, string $contentId): ?array
    {
        $type = $this->resolveContentType($type);
        if (null === $type) {
            return null;
        }

        try {
            $content = $this->contentManager->getRepository($type)->find($contentId);
        } catch (Throwable) {
            return null;
        }

        if (!$content instanceof ContentInterface) {
            return null;
        }

        return [$type, $content];
    }

    public function findContentById(string $contentId, ?string $type): ?array
    {
        foreach ($this->getContentTypes($type) as $resolvedType) {
            $result = $this->findContent($resolvedType, $contentId);
            if (null !== $result) {
                return $result;
            }
        }

        return null;
    }

    public function findContents(string $type, ?string $site, int $maxResults, bool $publishedOnly): array
    {
        try {
            $repository = $this->contentManager->getRepository($type);
            $qb = $repository->createQueryBuilder('content')
                ->setMaxResults($maxResults);

            $this->orderContents($qb, $repository->getClassName());

            if ($publishedOnly) {
                $qb->andWhere('content.publishedVersion IS NOT NULL');
            }

            if ($site) {
                $qb
                    ->innerJoin('content.sites', 'site')
                    ->andWhere('site.id = :site')
                    ->setParameter('site', $site);
            }

            return $qb->getQuery()->getResult();
        } catch (Throwable) {
            return [];
        }
    }

    public function orderContents(QueryBuilder $qb, string $className): void
    {
        $metadata = $qb->getEntityManager()->getClassMetadata($className);

        if ($metadata->hasField('name')) {
            $qb->addOrderBy('content.name', 'ASC');
        }

        if ($metadata->hasField('id')) {
            $qb->addOrderBy('content.id', 'ASC');
        }
    }

    public function contentMatchesQuery(ContentInterface $content, string $query): bool
    {
        if (false !== mb_stripos((string) $content->getName(), $query)) {
            return true;
        }

        foreach ([$content->getExtraData(), $content->getIndexing()] as $payload) {
            if ($this->payloadMatchesQuery($payload, $query)) {
                return true;
            }
        }

        foreach ([$content->getPublishedVersion(), $content->getLastVersion()] as $version) {
            if (!$version instanceof ContentVersionInterface) {
                continue;
            }

            foreach ([$version->getSeo(), $version->getData(), $version->getMeta()] as $payload) {
                if ($this->payloadMatchesQuery($payload, $query)) {
                    return true;
                }
            }
        }

        foreach ($content->getSites() as $site) {
            if (false !== mb_stripos((string) $site->getId(), $query)) {
                return true;
            }
        }

        return false;
    }

    public function payloadMatchesQuery(mixed $payload, string $query): bool
    {
        if (is_string($payload)) {
            return false !== mb_stripos($payload, $query);
        }

        if (is_array($payload)) {
            return false !== mb_stripos((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $query);
        }

        return false;
    }
}
