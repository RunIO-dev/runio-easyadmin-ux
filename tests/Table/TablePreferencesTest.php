<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Table;

use Runio\EasyAdminUxBundle\Table\SavedView;
use Runio\EasyAdminUxBundle\Table\TableLayout;
use Runio\EasyAdminUxBundle\Table\TablePreferences;
use Runio\EasyAdminUxBundle\Table\TooManySavedViewsException;
use PHPUnit\Framework\TestCase;

final class TablePreferencesTest extends TestCase
{
    public function testJsonRoundTrip(): void
    {
        $preferences = (new TablePreferences(TableLayout::create(['sku'], ['name'], TableLayout::DENSITY_COMPACT)))
            ->withView($this->view('a1', 'Low stock', ['sort' => ['stock' => 'ASC']]));

        $restored = TablePreferences::fromJson($preferences->toJson());

        self::assertSame(['sku'], $restored->layout->order);
        self::assertSame(TableLayout::DENSITY_COMPACT, $restored->layout->density);
        self::assertSame('Low stock', $restored->view('a1')?->name);
        self::assertSame(['sort' => ['stock' => 'ASC']], $restored->view('a1')?->query);
    }

    public function testCorruptedJsonGivesDefaults(): void
    {
        foreach ([null, '', 'not json', '42', '{"views":"x","layout":7}'] as $json) {
            $preferences = TablePreferences::fromJson($json);

            self::assertSame([], $preferences->views);
            self::assertSame([], $preferences->layout->order);
        }
    }

    public function testSavingSameNameReplacesViewAndKeepsId(): void
    {
        $preferences = (new TablePreferences())
            ->withView($this->view('first', 'Low stock', ['query' => 'a']))
            ->withView($this->view('second', 'low STOCK', ['query' => 'b']));

        self::assertCount(1, $preferences->views);
        self::assertSame('first', $preferences->views[0]->id);
        self::assertSame(['query' => 'b'], $preferences->views[0]->query);
    }

    public function testViewLimitIsEnforced(): void
    {
        $preferences = new TablePreferences();
        for ($i = 0; $i < TablePreferences::MAX_VIEWS; ++$i) {
            $preferences = $preferences->withView($this->view("id{$i}", "View {$i}"));
        }

        $this->expectException(TooManySavedViewsException::class);
        $preferences->withView($this->view('extra', 'One too many'));
    }

    public function testLayoutChangeOnlyAffectsItsScope(): void
    {
        $preferences = (new TablePreferences())->withView($this->view('v', 'View'));
        $compact = TableLayout::create([], [], TableLayout::DENSITY_COMPACT);

        $viewChanged = $preferences->withLayout($compact, 'v');
        self::assertSame(TableLayout::DENSITY_COMFORTABLE, $viewChanged->layout->density);
        self::assertSame(TableLayout::DENSITY_COMPACT, $viewChanged->view('v')?->layout->density);

        $baseChanged = $preferences->withLayout($compact, null);
        self::assertSame(TableLayout::DENSITY_COMPACT, $baseChanged->layout->density);
        self::assertSame(TableLayout::DENSITY_COMFORTABLE, $baseChanged->view('v')?->layout->density);
    }

    public function testViewCanBeDeleted(): void
    {
        $preferences = (new TablePreferences())->withView($this->view('v', 'View'))->withoutView('v');

        self::assertNull($preferences->view('v'));
    }

    /** @param array<string, mixed> $query */
    private function view(string $id, string $name, array $query = []): SavedView
    {
        return new SavedView($id, $name, $query, new TableLayout());
    }
}
