<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** A short list of records, such as the latest orders or items that need attention. */
abstract class ListWidget extends AbstractWidget
{
    /** @return iterable<ListItem> */
    abstract public function getItems(): iterable;

    /** Null shows the bundle's translated default message. */
    public function getEmptyMessage(): ?string
    {
        return null;
    }

    public function getMoreUrl(): ?string
    {
        return null;
    }

    public function render(): WidgetView
    {
        return new WidgetView('@RunioEasyAdminUx/dashboard/widget/list.html.twig', [
            'items' => array_map(
                static fn (ListItem $item): ListItem => new ListItem($item->label, WidgetUrl::sanitize($item->url), $item->meta),
                [...$this->getItems()],
            ),
            'empty_message' => $this->getEmptyMessage(),
            'more_url' => WidgetUrl::sanitize($this->getMoreUrl()),
        ]);
    }
}
