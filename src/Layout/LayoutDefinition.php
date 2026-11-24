<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Layout;

final readonly class LayoutDefinition
{
    public function __construct(
        public string $id,
        public string $label,
        public string $icon,
    ) {
    }
}
