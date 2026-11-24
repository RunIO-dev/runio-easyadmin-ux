<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Mapped so that the table is picked up by the application's Doctrine migrations.
 * Reads and writes go through DbalPreferenceStore.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'uniq_runio_ux_user_key', columns: ['user_identifier', 'preference_key'])]
class UserPreference
{
    public const TABLE = 'runio_ux_preference';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'user_identifier', length: 180)]
    private string $userIdentifier = '';

    #[ORM\Column(name: 'preference_key', length: 64)]
    private string $key = '';

    #[ORM\Column(name: 'preference_value', type: Types::TEXT)]
    private string $value = '';
}
