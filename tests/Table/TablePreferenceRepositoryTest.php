<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Table;

use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;
use Runio\EasyAdminUxBundle\Table\InvalidTableStateException;
use Runio\EasyAdminUxBundle\Table\SavedView;
use Runio\EasyAdminUxBundle\Table\TableLayout;
use Runio\EasyAdminUxBundle\Table\TablePreferenceRepository;
use Runio\EasyAdminUxBundle\Table\TablePreferences;
use PHPUnit\Framework\TestCase;

final class TablePreferenceRepositoryTest extends TestCase
{
    public function testPreferencesAreStoredPerUserAndTable(): void
    {
        $repository = new TablePreferenceRepository($this->store());
        $repository->save('ana', 'App\Product', new TablePreferences(TableLayout::create(['sku'])));

        self::assertSame(['sku'], $repository->load('ana', 'App\Product')->layout->order);
        self::assertSame([], $repository->load('sam', 'App\Product')->layout->order);
        self::assertSame([], $repository->load('ana', 'App\Category')->layout->order);
    }

    public function testOversizedPreferencesAreRejectedWithoutStoring(): void
    {
        $store = $this->store();
        $repository = new TablePreferenceRepository($store);
        $view = new SavedView('big', 'Big', ['query' => str_repeat('a', TablePreferenceRepository::MAX_BYTES)], new TableLayout());

        try {
            $repository->save('ana', 'App\Product', (new TablePreferences())->withView($view));
            self::fail('Expected InvalidTableStateException.');
        } catch (InvalidTableStateException) {
            self::assertSame([], $repository->load('ana', 'App\Product')->views);
        }
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
