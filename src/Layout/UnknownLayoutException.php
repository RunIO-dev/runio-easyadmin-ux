<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Layout;

final class UnknownLayoutException extends \InvalidArgumentException
{
    public static function forId(string $id): self
    {
        return new self(\sprintf('Layout "%s" is not registered.', $id));
    }
}
