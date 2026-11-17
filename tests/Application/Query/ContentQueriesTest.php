<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests\Application\Query;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\Application\Query\ContentProvider;
use Softspring\CmsApiPlugin\Application\Query\ContentQuery;
use Softspring\CmsApiPlugin\Application\Query\PublishedContentQuery;
use Softspring\CmsBundle\Config\CmsConfig;
use Softspring\CmsBundle\Manager\ContentManagerInterface;
use Softspring\CmsBundle\Model\ContentInterface;
use Softspring\CmsBundle\Model\ContentVersionInterface;
use Softspring\CmsBundle\Serialization\PublishedContentSerializer;

class ContentQueriesTest extends TestCase
{
    public function testRestAndPublishedQueriesResolvePluralTypesThroughTheSameProvider(): void
    {
        $content = $this->createStub(ContentInterface::class);
        $content->method('getId')->willReturn('content-1');
        $content->method('getSites')->willReturn(new ArrayCollection());
        $content->method('getRoutes')->willReturn(new ArrayCollection());
        $version = $this->createStub(ContentVersionInterface::class);
        $content->method('getPublishedVersion')->willReturn($version);
        $provider = $this->provider($content, 2);

        $rest = new ContentQuery($provider)->get('pages', 'content-1');
        $published = new PublishedContentQuery($provider, new PublishedContentSerializer())
            ->findPublishedContentById('content-1', 'pages');

        self::assertSame('content-1', $rest['id']);
        self::assertSame('page', $rest['type']);
        self::assertSame(['page', $content, $version], $published);
    }

    public function testPublishedLookupDoesNotReturnUnpublishedContent(): void
    {
        $content = $this->createStub(ContentInterface::class);
        $content->method('getPublishedVersion')->willReturn(null);
        $query = new PublishedContentQuery($this->provider($content, 1), new PublishedContentSerializer());

        self::assertNull($query->findPublishedContentById('content-1', 'pages'));
    }

    public function testUnknownContentTypesDoNotQueryTheRepository(): void
    {
        $config = $this->createStub(CmsConfig::class);
        $config->method('getContent')->willReturn(null);
        $manager = $this->createMock(ContentManagerInterface::class);
        $manager->expects(self::never())->method('getRepository');
        $provider = new ContentProvider($config, $manager);

        self::assertNull(new ContentQuery($provider)->get('unknown', 'content-1'));
        self::assertNull(new PublishedContentQuery($provider, new PublishedContentSerializer())->searchPublished('unknown', '', null, null, 10));
    }

    private function provider(ContentInterface $content, int $calls): ContentProvider
    {
        $config = $this->createStub(CmsConfig::class);
        $config->method('getContent')->willReturnCallback(static fn(string $type): ?array => 'page' === $type ? ['_id' => 'page'] : null);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::exactly($calls))->method('find')->with('content-1')->willReturn($content);
        $manager = $this->createMock(ContentManagerInterface::class);
        $manager->expects(self::exactly($calls))->method('getRepository')->with('page')->willReturn($repository);

        return new ContentProvider($config, $manager);
    }
}
