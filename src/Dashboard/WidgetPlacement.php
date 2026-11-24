<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

final class WidgetPlacement
{
    public function __construct(
        public readonly string $id,
        public readonly WidgetSize $size,
    ) {
    }
}
