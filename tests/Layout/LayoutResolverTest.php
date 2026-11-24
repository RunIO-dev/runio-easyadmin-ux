<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Layout;

use Runio\EasyAdminUxBundle\Layout\LayoutDefinition;
use Runio\EasyAdminUxBundle\Layout\LayoutRegistry;
use Runio\EasyAdminUxBundle\Layout\LayoutResolver;
use Runio\EasyAdminUxBundle\Layout\UnknownLayoutException;
use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;
use PHPUnit\Framework\TestCase;

final class LayoutResolverTest extends TestCase
{
    public function testAnonymousUserGetsDefaultLayout(): void
    {
        $store = $this->store();

        self::assertSame('sidebar', $this->resolver($store)->resolve(null)->id);
    }

    public function testUserWithoutPreferenceGetsDefaultLayout(): void
    {
        self::assertSame('sidebar', $this->resolver($this->store())->resolve('ana')->id);
    }

    public function testSavedPreferenceIsUsedPerUser(): void
    {
        $store = $this->store();
        $resolver = $this->resolver($store);

        $resolver->save('ana', 'topbar');

        self::assertSame('topbar', $resolver->resolve('ana')->id);
        self::assertSame('sidebar', $resolver->resolve('sam')->id);
    }

    public function testRemovedLayoutFallsBackToDefault(): void
    {
        $store = $this->store(['ana' => 'layout-that-was-removed']);

        self::assertSame('sidebar', $this->resolver($store)->resolve('ana')->id);
    }

    public function testSavingUnknownLayoutIsRejectedAndNotStored(): void
    {
        $store = $this->store();

        try {
            $this->resolver($store)->save('ana', 'nope');
            self::fail('Expected UnknownLayoutException.');
        } catch (UnknownLayoutException) {
            self::assertNull($store->get('ana', LayoutResolver::PREFERENCE_KEY));
        }
    }

    public function testRegistryRejectsDefaultThatIsNotRegistered(): void
    {
        $this->expectException(UnknownLayoutException::class);

        new LayoutRegistry([new LayoutDefinition('sidebar', 'Sidebar', 'sidebar')], 'topbar');
    }

    private function resolver(PreferenceStoreInterface $store): LayoutResolver
    {
        return new LayoutResolver(new LayoutRegistry([
            new LayoutDefinition('sidebar', 'Sidebar', 'sidebar'),
            new LayoutDefinition('topbar', 'Top navigation', 'topbar'),
        ], 'sidebar'), $store);
    }

    /** @param array<string, string> $layouts */
    private function store(array $layouts = []): PreferenceStoreInterface
    {
        return new class($layouts) implements PreferenceStoreInterface {
            /** @var array<string, string> */
            private array $values = [];

            /** @param array<string, string> $layouts */
            public function __construct(array $layouts)
            {
                foreach ($layouts as $user => $layout) {
                    $this->values[$user.'|'.LayoutResolver::PREFERENCE_KEY] = $layout;
                }
            }

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
