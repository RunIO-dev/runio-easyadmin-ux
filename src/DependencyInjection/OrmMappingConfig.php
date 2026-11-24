<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\DependencyInjection;

final class OrmMappingConfig
{
    private const MAPPING_NAME = 'RunioEasyAdminUxBundle';

    /**
     * Builds Doctrine config matching the application's ORM style, because the single-manager
     * shorthand can't be combined with an explicit "entity_managers" section.
     *
     * @param list<array<string, mixed>> $doctrineConfigs
     * @param array<string, mixed>       $mapping
     *
     * @return array<string, mixed>|null null when the application doesn't use the ORM
     */
    public static function build(array $doctrineConfigs, array $mapping): ?array
    {
        $usesOrm = false;
        $managers = [];
        $defaultManager = null;

        foreach ($doctrineConfigs as $config) {
            if (!\array_key_exists('orm', $config)) {
                continue;
            }

            $usesOrm = true;
            $managers += $config['orm']['entity_managers'] ?? [];
            $defaultManager = $config['orm']['default_entity_manager'] ?? $defaultManager;
        }

        if (!$usesOrm) {
            return null;
        }

        $mappings = ['mappings' => [self::MAPPING_NAME => $mapping]];
        if ([] === $managers) {
            return ['orm' => $mappings];
        }

        $target = match (true) {
            null !== $defaultManager && isset($managers[$defaultManager]) => $defaultManager,
            isset($managers['default']) => 'default',
            default => array_key_first($managers),
        };

        return ['orm' => ['entity_managers' => [$target => $mappings]]];
    }
}
