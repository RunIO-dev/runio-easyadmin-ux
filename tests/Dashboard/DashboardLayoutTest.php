<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Dashboard;

use PHPUnit\Framework\TestCase;
use Runio\EasyAdminUxBundle\Dashboard\DashboardLayout;
use Runio\EasyAdminUxBundle\Dashboard\WidgetPlacement;
use Runio\EasyAdminUxBundle\Dashboard\WidgetSize;

final class DashboardLayoutTest extends TestCase
{
    public function testUncustomizedLayoutShowsDefaultWidgetsInRegistryOrder(): void
    {
        $available = $this->widgets(new TestWidget('sales', size: WidgetSize::Small), new TestWidget('hidden', shownByDefault: false), new TestWidget('orders'));

        self::assertEquals([
            new WidgetPlacement('sales', WidgetSize::Small),
            new WidgetPlacement('orders', WidgetSize::Medium),
        ], (new DashboardLayout())->resolve($available));
    }

    public function testCustomizedLayoutKeepsOrderAndSizesAndDropsUnavailableWidgets(): void
    {
        $available = $this->widgets(new TestWidget('sales'), new TestWidget('orders'));
        $layout = DashboardLayout::customize([['id' => 'orders', 'size' => 'full'], ['id' => 'sales', 'size' => 'small']], $available);

        $fewer = $this->widgets(new TestWidget('orders'));

        self::assertEquals([new WidgetPlacement('orders', WidgetSize::Full), new WidgetPlacement('sales', WidgetSize::Small)], $layout->resolve($available));
        self::assertEquals([new WidgetPlacement('orders', WidgetSize::Full)], $layout->resolve($fewer));
    }

    public function testRemovedWidgetsStayRemovedButNewDefaultWidgetsAreAppended(): void
    {
        $layout = DashboardLayout::customize([['id' => 'orders', 'size' => 'medium']], $this->widgets(new TestWidget('sales'), new TestWidget('orders')));

        $later = $this->widgets(new TestWidget('sales'), new TestWidget('orders'), new TestWidget('returns', size: WidgetSize::Large), new TestWidget('optional', shownByDefault: false));

        self::assertEquals([
            new WidgetPlacement('orders', WidgetSize::Medium),
            new WidgetPlacement('returns', WidgetSize::Large),
        ], $layout->resolve($later));
    }

    public function testCustomizeIgnoresUnknownAndDuplicateWidgetsAndFallsBackToTheDefaultSize(): void
    {
        $available = $this->widgets(new TestWidget('sales', size: WidgetSize::Small));
        $layout = DashboardLayout::customize([
            ['id' => 'sales', 'size' => 'enormous'],
            ['id' => 'sales', 'size' => 'full'],
            ['id' => 'secret', 'size' => 'full'],
            ['size' => 'full'],
            'garbage',
        ], $available);

        self::assertEquals([new WidgetPlacement('sales', WidgetSize::Small)], $layout->placements);
        self::assertTrue($layout->customized);
    }

    public function testCustomizeKeepsAtMostTheMaximumNumberOfWidgets(): void
    {
        $widgets = [];
        $requested = [];
        for ($i = 0; $i < DashboardLayout::MAX_WIDGETS + 5; ++$i) {
            $widgets[] = new TestWidget('w'.$i);
            $requested[] = ['id' => 'w'.$i, 'size' => 'small'];
        }

        self::assertCount(DashboardLayout::MAX_WIDGETS, DashboardLayout::customize($requested, $this->widgets(...$widgets))->placements);
    }

    public function testAWidgetDeployedAfterThePageLoadedStillAppearsLater(): void
    {
        $available = $this->widgets(new TestWidget('sales'), new TestWidget('orders'), new TestWidget('returns'));

        // The page that saved this layout was rendered before "returns" existed.
        $layout = DashboardLayout::customize([['id' => 'sales', 'size' => 'small']], $available, ['sales', 'orders', 'gone']);

        self::assertSame(['sales', 'orders'], $layout->known);
        self::assertSame(['sales', 'returns'], array_map(static fn (WidgetPlacement $p): string => $p->id, $layout->resolve($available)));
    }

    public function testAnEmptyCustomizedLayoutStaysEmpty(): void
    {
        $available = $this->widgets(new TestWidget('sales'));

        self::assertSame([], DashboardLayout::customize([], $available)->resolve($available));
    }

    public function testRoundTripsThroughArraysAndToleratesCorruptData(): void
    {
        $available = $this->widgets(new TestWidget('sales'), new TestWidget('orders'));
        $layout = DashboardLayout::customize([['id' => 'orders', 'size' => 'large']], $available);

        self::assertEquals($layout, DashboardLayout::fromArray($layout->toArray()));
        self::assertEquals(new DashboardLayout(), DashboardLayout::fromArray(['placements' => 'x', 'known' => [1, []], 'customized' => 'yes']));
        self::assertEquals(
            [new WidgetPlacement('orders', WidgetSize::Large)],
            DashboardLayout::fromArray(['customized' => true, 'known' => ['orders'], 'placements' => [['id' => 'orders', 'size' => 'large'], ['id' => 'Bad Id!', 'size' => 'small'], ['id' => 'x', 'size' => 'nope']]])->placements,
        );
    }

    public function testHiddenWidgetsAreTheAvailableOnesNotPlaced(): void
    {
        $available = $this->widgets(new TestWidget('sales'), new TestWidget('orders'), new TestWidget('extra', shownByDefault: false));

        self::assertSame(['orders', 'extra'], array_keys(DashboardLayout::customize([['id' => 'sales', 'size' => 'small']], $available)->hidden($available)));
    }

    /** @return array<string, TestWidget> */
    private function widgets(TestWidget ...$widgets): array
    {
        $byId = [];
        foreach ($widgets as $widget) {
            $byId[$widget->getId()] = $widget;
        }

        return $byId;
    }
}
