<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** How many of the dashboard's four columns a widget spans on wide screens. */
enum WidgetSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
    case Full = 'full';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
