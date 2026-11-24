<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** The Twig template, and its variables, that render a widget's body. */
final class WidgetView
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        public readonly string $template,
        public readonly array $parameters = [],
    ) {
    }
}
