<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\DependencyInjection;

use Runio\EasyAdminUxBundle\DependencyInjection\OrmMappingConfig;
use PHPUnit\Framework\TestCase;

final class OrmMappingConfigTest extends TestCase
{
    private const MAPPING = ['type' => 'attribute'];

    public function testDbalOnlyAppGetsNoOrmConfig(): void
    {
        self::assertNull(OrmMappingConfig::build([['dbal' => ['url' => 'x']]], self::MAPPING));
    }

    public function testSingleManagerShorthandIsUsed(): void
    {
        self::assertSame(
            ['orm' => ['mappings' => ['RunioEasyAdminUxBundle' => self::MAPPING]]],
            OrmMappingConfig::build([['orm' => ['auto_mapping' => true]]], self::MAPPING),
        );
    }

    public function testConfiguredDefaultManagerIsTargeted(): void
    {
        $configs = [['orm' => [
            'default_entity_manager' => 'main',
            'entity_managers' => ['other' => [], 'main' => []],
        ]]];

        self::assertSame(
            ['orm' => ['entity_managers' => ['main' => ['mappings' => ['RunioEasyAdminUxBundle' => self::MAPPING]]]]],
            OrmMappingConfig::build($configs, self::MAPPING),
        );
    }

    public function testFirstManagerIsUsedWithoutDefault(): void
    {
        $configs = [['orm' => ['entity_managers' => ['first' => [], 'second' => []]]]];

        self::assertArrayHasKey('first', OrmMappingConfig::build($configs, self::MAPPING)['orm']['entity_managers']);
    }
}
