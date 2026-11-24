<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Dashboard;

use PHPUnit\Framework\TestCase;
use Runio\EasyAdminUxBundle\Dashboard\WidgetRegistry;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class WidgetRegistryTest extends TestCase
{
    public function testOnlyWidgetsTheUserIsGrantedAreAvailable(): void
    {
        $registry = new WidgetRegistry([new TestWidget('sales'), new TestWidget('payroll', permission: 'ROLE_HR')], $this->checker(['ROLE_ADMIN']));

        self::assertSame(['sales', 'payroll'], array_keys($registry->all()));
        self::assertSame(['sales'], array_keys($registry->available()));
    }

    public function testDuplicateIdsAreRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"sales"');

        (new WidgetRegistry([new TestWidget('sales'), new TestWidget('sales')], $this->checker([])))->all();
    }

    /** @return iterable<array{string}> */
    public static function invalidIds(): iterable
    {
        yield 'spaces and capitals' => ['Not valid'];
        // PHP turns numeric array keys into integers, which would break the string-typed layout.
        yield 'digits only' => ['2024'];
        yield 'starts with a digit' => ['7days'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIds')]
    public function testInvalidIdsAreRejected(string $id): void
    {
        $this->expectException(\LogicException::class);

        (new WidgetRegistry([new TestWidget($id)], $this->checker([])))->all();
    }

    /** @param list<string> $granted */
    private function checker(array $granted): AuthorizationCheckerInterface
    {
        return new class($granted) implements AuthorizationCheckerInterface {
            /** @param list<string> $granted */
            public function __construct(private readonly array $granted)
            {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };
    }
}
