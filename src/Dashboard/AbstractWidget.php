<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

abstract class AbstractWidget implements WidgetInterface
{
    /** Widgets with a higher priority come first on a dashboard that hasn't been customized. */
    public static function getDefaultPriority(): int
    {
        return 0;
    }

    public function getDefaultSize(): WidgetSize
    {
        return WidgetSize::Medium;
    }

    public function isShownByDefault(): bool
    {
        return true;
    }

    public function getRequiredPermission(): ?string
    {
        return null;
    }
}
