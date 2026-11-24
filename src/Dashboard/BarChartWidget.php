<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** Compares a few values as horizontal bars, without any charting library. */
abstract class BarChartWidget extends AbstractWidget
{
    /** @return iterable<string, int|float> Values keyed by their label */
    abstract public function getBars(): iterable;

    public function render(): WidgetView
    {
        $bars = [];
        foreach ($this->getBars() as $label => $value) {
            $bars[] = ['label' => (string) $label, 'value' => $value];
        }

        $max = max([0, ...array_column($bars, 'value')]);
        foreach ($bars as $i => $bar) {
            $bars[$i]['percent'] = $max > 0 ? max(0, round($bar['value'] / $max * 100, 1)) : 0;
        }

        return new WidgetView('@RunioEasyAdminUx/dashboard/widget/bar_chart.html.twig', ['bars' => $bars]);
    }
}
