<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** A single headline number, such as open orders or revenue this month. */
abstract class MetricWidget extends AbstractWidget
{
    /** Already formatted for display, because only the application knows its units and locale. */
    abstract public function getValue(): string|int|float;

    public function getHint(): ?string
    {
        return null;
    }

    public function getUrl(): ?string
    {
        return null;
    }

    public function getDefaultSize(): WidgetSize
    {
        return WidgetSize::Small;
    }

    public function render(): WidgetView
    {
        return new WidgetView('@RunioEasyAdminUx/dashboard/widget/metric.html.twig', [
            'value' => $this->getValue(),
            'hint' => $this->getHint(),
            'url' => WidgetUrl::sanitize($this->getUrl()),
        ]);
    }
}
