<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Dashboard;

use PHPUnit\Framework\TestCase;
use Runio\EasyAdminUxBundle\Dashboard\DashboardLayout;
use Runio\EasyAdminUxBundle\Dashboard\DashboardPreferenceRepository;
use Runio\EasyAdminUxBundle\Dashboard\DashboardRenderer;
use Runio\EasyAdminUxBundle\Dashboard\WidgetRegistry;
use Runio\EasyAdminUxBundle\Dashboard\WidgetView;
use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class DashboardRendererTest extends TestCase
{
    public function testRendersTheUsersWidgetsInOrderAndListsTheHiddenOnes(): void
    {
        $repository = new DashboardPreferenceRepository($this->store());
        $registry = $this->registry(new TestWidget('sales'), new TestWidget('orders'), new TestWidget('extra', shownByDefault: false));
        $repository->save('ana', 'main', DashboardLayout::customize([['id' => 'orders', 'size' => 'full'], ['id' => 'sales', 'size' => 'small']], $registry->available()));

        $view = (new DashboardRenderer($registry, $repository, false))->prepare($this->twig(), 'main', 'ana');

        self::assertSame(['orders', 'sales'], array_column($view['widgets'], 'id'));
        self::assertSame(['full', 'small'], array_column($view['widgets'], 'size'));
        self::assertSame('<b>orders</b>', $view['widgets'][0]['html']);
        self::assertSame(['extra'], array_column($view['hidden'], 'id'));
    }

    public function testAFailingWidgetIsIsolatedOutsideDebugMode(): void
    {
        $registry = $this->registry(new TestWidget('broken', renderer: static fn (): WidgetView => throw new \RuntimeException('database down')), new TestWidget('sales'));

        $view = (new DashboardRenderer($registry, new DashboardPreferenceRepository($this->store()), false))->prepare($this->twig(), 'main', 'ana');

        self::assertNull($view['widgets'][0]['html']);
        self::assertTrue($view['widgets'][0]['failed']);
        self::assertSame('<b>sales</b>', $view['widgets'][1]['html']);
    }

    public function testAFailingWidgetIsReportedInDebugMode(): void
    {
        $registry = $this->registry(new TestWidget('broken', renderer: static fn (): WidgetView => throw new \RuntimeException('database down')));

        $this->expectExceptionMessage('database down');

        (new DashboardRenderer($registry, new DashboardPreferenceRepository($this->store()), true))->prepare($this->twig(), 'main', 'ana');
    }

    public function testLayoutsAreSeparatePerUserAndDashboard(): void
    {
        $repository = new DashboardPreferenceRepository($this->store());
        $registry = $this->registry(new TestWidget('sales'), new TestWidget('orders'));
        $repository->save('ana', 'main', DashboardLayout::customize([['id' => 'orders', 'size' => 'small']], $registry->available()));

        $renderer = new DashboardRenderer($registry, $repository, false);

        self::assertSame(['orders'], array_column($renderer->prepare($this->twig(), 'main', 'ana')['widgets'], 'id'));
        self::assertSame(['sales', 'orders'], array_column($renderer->prepare($this->twig(), 'main', 'sam')['widgets'], 'id'));
        self::assertSame(['sales', 'orders'], array_column($renderer->prepare($this->twig(), 'sales', 'ana')['widgets'], 'id'));
        self::assertNotSame(DashboardPreferenceRepository::csrfTokenId('main'), DashboardPreferenceRepository::csrfTokenId('sales'));
    }

    private function registry(TestWidget ...$widgets): WidgetRegistry
    {
        return new WidgetRegistry($widgets, new class implements AuthorizationCheckerInterface {
            public function isGranted(mixed $attribute, mixed $subject = null, ?\Symfony\Component\Security\Core\Authorization\AccessDecision $accessDecision = null): bool
            {
                return true;
            }
        });
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader(['widget.twig' => '<b>{{ id }}</b>']), ['strict_variables' => true]);
    }

    private function store(): PreferenceStoreInterface
    {
        return new class implements PreferenceStoreInterface {
            /** @var array<string, string> */
            private array $values = [];

            public function get(string $userIdentifier, string $key): ?string
            {
                return $this->values[$userIdentifier.'|'.$key] ?? null;
            }

            public function set(string $userIdentifier, string $key, string $value): void
            {
                $this->values[$userIdentifier.'|'.$key] = $value;
            }
        };
    }
}
