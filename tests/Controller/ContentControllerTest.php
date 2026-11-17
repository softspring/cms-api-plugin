<?php

declare(strict_types=1);

namespace Softspring\CmsApiPlugin\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Softspring\CmsApiPlugin\Api\RequestQuery;
use Softspring\CmsApiPlugin\Controller\ContentController;
use Softspring\CmsApiPlugin\Application\Query\ContentQuery;
use Symfony\Component\HttpFoundation\Request;

class ContentControllerTest extends TestCase
{
    public function testPublishedListUsesTheSharedQueryAndPreservesTheHttpContract(): void
    {
        $data = ['type' => 'page', 'count' => 0, 'content' => []];
        $query = $this->createMock(ContentQuery::class);
        $query->expects(self::once())->method('list')
            ->with('pages', 'guide', 'default', 'en', true, 100)->willReturn($data);
        $controller = new ContentController($query, new RequestQuery());
        $request = Request::create('/content/pages', 'GET', [
            'query' => 'guide', 'site' => 'default', 'locale' => 'en', 'publishedOnly' => 'true', 'limit' => 900,
        ]);

        $response = $controller->list('pages', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($data, json_decode($response->getContent(), true));
    }

    public function testUnknownTypeRemainsAnHttpNotFoundResponse(): void
    {
        $query = $this->createMock(ContentQuery::class);
        $query->expects(self::once())->method('list')->willReturn(null);
        $controller = new ContentController($query, new RequestQuery());

        $response = $controller->list('missing', Request::create('/content/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'Content type "missing" was not found.'], json_decode($response->getContent(), true));
    }
}
