<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** The url must point to a web page or email address; any other scheme is dropped when rendered. */
final class ListItem
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $url = null,
        public readonly ?string $meta = null,
    ) {
    }
}
