<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Table;

use Runio\EasyAdminUxBundle\Table\InvalidTableStateException;
use Runio\EasyAdminUxBundle\Table\TableQuery;
use PHPUnit\Framework\TestCase;

final class TableQueryTest extends TestCase
{
    public function testListStateAndContextAreKeptWithoutTransientParameters(): void
    {
        $query = TableQuery::fromQueryString('query=desk&sort[stock]=ASC&filters[active]=1&page=4&ux_view=abc&ux_drawer=1&referrer=/x&scope=archived');

        self::assertEquals([
            'scope' => 'archived',
            'query' => 'desk',
            'sort' => ['stock' => 'ASC'],
            'filters' => ['active' => '1'],
        ], $query);
    }

    public function testContextExcludesListState(): void
    {
        self::assertSame(['scope' => 'archived'], TableQuery::context([
            'scope' => 'archived',
            'query' => 'desk',
            'sort' => ['stock' => 'ASC'],
            'filters' => ['active' => '1'],
            'page' => '2',
            'ux_view' => 'abc',
            'referrer' => '/x',
        ]));
    }

    public function testInvalidSortIsDropped(): void
    {
        $query = TableQuery::fromQueryString('sort[stock]=SIDEWAYS&sort[bad name;]=ASC&sort[name]=DESC');

        self::assertSame(['sort' => ['name' => 'DESC']], $query);
    }

    public function testScalarFiltersAreDropped(): void
    {
        self::assertSame([], TableQuery::fromQueryString('filters=abc'));
    }

    public function testOversizedQueryIsRejected(): void
    {
        $this->expectException(InvalidTableStateException::class);

        TableQuery::fromQueryString('query='.str_repeat('a', TableQuery::MAX_LENGTH + 1));
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectException(InvalidTableStateException::class);

        TableQuery::fromQueryString('filters[name][value]=%FF');
    }

    public function testUrlIncludesViewId(): void
    {
        self::assertSame(
            '/admin/product?query=desk&ux_view=abc',
            TableQuery::url('/admin/product', ['query' => 'desk'], 'abc'),
        );
        self::assertSame('/admin/product', TableQuery::url('/admin/product', [], null));
    }
}
