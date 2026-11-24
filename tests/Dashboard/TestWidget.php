<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Dashboard;

use Runio\EasyAdminUxBundle\Dashboard\AbstractWidget;
use Runio\EasyAdminUxBundle\Dashboard\WidgetSize;
use Runio\EasyAdminUxBundle\Dashboard\WidgetView;

final class TestWidget extends AbstractWidget
{
    public function __construct(
        private readonly string $id,
        private readonly bool $shownByDefault = true,
        private readonly WidgetSize $size = WidgetSize::Medium,
        private readonly ?string $permission = null,
        private readonly ?\Closure $renderer = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return 'Label '.$this->id;
    }

    public function getDefaultSize(): WidgetSize
    {
        return $this->size;
    }

    public function isShownByDefault(): bool
    {
        return $this->shownByDefault;
    }

    public function getRequiredPermission(): ?string
    {
        return $this->permission;
    }

    public function render(): WidgetView
    {
        return null !== $this->renderer ? ($this->renderer)() : new WidgetView('widget.twig', ['id' => $this->id]);
    }
}
