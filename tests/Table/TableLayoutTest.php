<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Table;

use Runio\EasyAdminUxBundle\Table\TableLayout;
use PHPUnit\Framework\TestCase;

final class TableLayoutTest extends TestCase
{
    private const COLUMNS = ['name', 'sku', 'price', 'stock'];

    public function testDefaultLayoutKeepsConfiguredOrder(): void
    {
        self::assertSame(self::COLUMNS, (new TableLayout())->visible(self::COLUMNS));
    }

    public function testSavedOrderAndHiddenColumnsAreApplied(): void
    {
        $layout = TableLayout::create(['stock', 'name', 'price', 'sku'], ['price']);

        self::assertSame(['stock', 'name', 'price', 'sku'], $layout->ordered(self::COLUMNS));
        self::assertSame(['stock', 'name', 'sku'], $layout->visible(self::COLUMNS));
    }

    public function testNewColumnAppearsAfterItsConfiguredNeighbour(): void
    {
        $layout = TableLayout::create(['stock', 'name', 'sku']);

        self::assertSame(['stock', 'name', 'sku', 'price'], $layout->ordered(self::COLUMNS));
        self::assertSame(['id', 'b', 'a'], TableLayout::create(['b', 'a'])->ordered(['id', 'a', 'b']));
    }

    public function testRemovedColumnsAreIgnored(): void
    {
        $layout = TableLayout::create(['gone', 'sku', 'name'], ['gone']);

        self::assertSame(['sku', 'name'], $layout->visible(['name', 'sku']));
    }

    public function testHidingEveryColumnShowsThemAll(): void
    {
        $layout = TableLayout::create([], self::COLUMNS);

        self::assertSame(self::COLUMNS, $layout->visible(self::COLUMNS));
    }

    public function testInvalidInputIsSanitized(): void
    {
        $layout = TableLayout::create(['name', 'name', 42, str_repeat('x', 101), "bad\xFF"], [null, 'sku'], 'huge');

        self::assertSame(['name'], $layout->order);
        self::assertSame(['sku'], $layout->hidden);
        self::assertSame(TableLayout::DENSITY_COMFORTABLE, $layout->density);
    }
}
