<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Layout;

use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;

final class LayoutResolver
{
    public const PREFERENCE_KEY = 'layout';

    public function __construct(
        private readonly LayoutRegistry $registry,
        private readonly PreferenceStoreInterface $store,
    ) {
    }

    public function resolve(?string $userIdentifier): LayoutDefinition
    {
        if (null === $userIdentifier) {
            return $this->registry->getDefault();
        }

        $saved = $this->store->get($userIdentifier, self::PREFERENCE_KEY);

        return null !== $saved && $this->registry->has($saved)
            ? $this->registry->get($saved)
            : $this->registry->getDefault();
    }

    public function save(string $userIdentifier, string $layoutId): void
    {
        $layout = $this->registry->get($layoutId);

        $this->store->set($userIdentifier, self::PREFERENCE_KEY, $layout->id);
    }
}
