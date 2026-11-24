<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

use Runio\EasyAdminUxBundle\Preference\PreferenceStoreInterface;

final class DashboardPreferenceRepository
{
    public function __construct(private readonly PreferenceStoreInterface $store)
    {
    }

    public function load(string $userIdentifier, string $dashboard): DashboardLayout
    {
        $json = $this->store->get($userIdentifier, self::key($dashboard));

        try {
            $data = null === $json ? null : json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $data = null;
        }

        return \is_array($data) ? DashboardLayout::fromArray($data) : new DashboardLayout();
    }

    public function save(string $userIdentifier, string $dashboard, DashboardLayout $layout): void
    {
        $this->store->set($userIdentifier, self::key($dashboard), json_encode($layout->toArray(), \JSON_THROW_ON_ERROR));
    }

    /** The token binds a request to a dashboard the server actually rendered for this session. */
    public static function csrfTokenId(string $dashboard): string
    {
        return 'runio_ux_dashboard_'.sha1($dashboard);
    }

    private static function key(string $dashboard): string
    {
        return 'dashboard:'.sha1($dashboard);
    }
}
