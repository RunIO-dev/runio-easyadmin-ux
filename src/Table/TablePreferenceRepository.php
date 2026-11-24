<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;

final class TablePreferenceRepository
{
    /** Stays well below the 64 KB limit of a MySQL TEXT column, so values are never truncated. */
    public const MAX_BYTES = 32768;

    public function __construct(private readonly PreferenceStoreInterface $store)
    {
    }

    public function load(string $userIdentifier, string $crudController): TablePreferences
    {
        return TablePreferences::fromJson($this->store->get($userIdentifier, self::key($crudController)));
    }

    public function save(string $userIdentifier, string $crudController, TablePreferences $preferences): void
    {
        $json = $preferences->toJson();
        if (\strlen($json) > self::MAX_BYTES) {
            throw new InvalidTableStateException('This table has too many saved settings. Delete a saved view and try again.');
        }

        $this->store->set($userIdentifier, self::key($crudController), $json);
    }

    /** The token binds a request to a table the server actually rendered for this session. */
    public static function csrfTokenId(string $crudController): string
    {
        return 'runio_ux_table_'.sha1($crudController);
    }

    private static function key(string $crudController): string
    {
        return 'table:'.sha1($crudController);
    }
}
