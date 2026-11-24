<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Preference;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Runio\EasyAdminUxBundle\Entity\UserPreference;

/**
 * Uses DBAL directly so that saving a preference never flushes unrelated ORM changes.
 */
final class DbalPreferenceStore implements PreferenceStoreInterface
{
    private const MAX_IDENTIFIER_LENGTH = 180;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function get(string $userIdentifier, string $key): ?string
    {
        try {
            $value = $this->connection->fetchOne(
                \sprintf('SELECT preference_value FROM %s WHERE user_identifier = ? AND preference_key = ?', UserPreference::TABLE),
                [$this->storedIdentifier($userIdentifier), $key],
            );
        } catch (TableNotFoundException) {
            // The admin must keep working between installing the bundle and running its migration.
            return null;
        }

        return false === $value ? null : (string) $value;
    }

    public function set(string $userIdentifier, string $key, string $value): void
    {
        $identifier = $this->storedIdentifier($userIdentifier);

        try {
            $this->connection->insert(UserPreference::TABLE, [
                'user_identifier' => $identifier,
                'preference_key' => $key,
                'preference_value' => $value,
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->connection->update(
                UserPreference::TABLE,
                ['preference_value' => $value],
                ['user_identifier' => $identifier, 'preference_key' => $key],
            );
        }
    }

    private function storedIdentifier(string $userIdentifier): string
    {
        return mb_strlen($userIdentifier) > self::MAX_IDENTIFIER_LENGTH
            ? 'sha256:'.hash('sha256', $userIdentifier)
            : $userIdentifier;
    }
}
