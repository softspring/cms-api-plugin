<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Application\Query;

use InvalidArgumentException;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsSeoPlugin\Content\WordStatistics\VersionWordStatistics;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SeoStatisticsQuery
{
    public function __construct(
        private readonly ContentProvider $contents,
        private readonly VersionWordStatistics $statistics,
        private readonly AuthorizationCheckerInterface $security,
    ) {}

    public function analyze(string $type, string $contentId, string $versionId, ?string $locale = null, ?string $site = null, ?int $limit = null): array
    {
        if (!$this->security->isGranted('CMS_API_READ', ['resource' => 'seo_statistics', 'type' => $type, 'content' => $contentId])) {
            throw new AccessDeniedHttpException('Missing permission CMS_API_READ.');
        }
        $content = $this->contents->findContent($type, $contentId)[1] ?? throw new NotFoundHttpException('Content was not found.');
        $version = $content->getVersions()->filter(static fn(ContentVersionInterface $item): bool => (string) $item->getId() === $versionId)->first();
        if (!$version instanceof ContentVersionInterface) {
            throw new NotFoundHttpException('Version was not found for this content.');
        }
        try {
            return $this->statistics->analyze($version, $locale, $site, $limit);
        } catch (InvalidArgumentException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }
    }
}
