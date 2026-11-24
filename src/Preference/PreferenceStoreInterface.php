<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Preference;

interface PreferenceStoreInterface
{
    public function get(string $userIdentifier, string $key): ?string;

    public function set(string $userIdentifier, string $key, string $value): void;
}
