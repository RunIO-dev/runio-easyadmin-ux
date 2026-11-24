<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/**
 * A dashboard card. Services implementing this interface are registered automatically when
 * autoconfiguration is enabled; otherwise tag them with "runio_ux.dashboard_widget".
 * A public static getDefaultPriority(): int method orders them; higher comes first.
 */
interface WidgetInterface
{
    /** Stable identifier stored in user layouts: a lowercase letter, then letters, digits, ".", "_" or "-". */
    public function getId(): string;

    /** Title shown on the card, translated with the application's default domain. */
    public function getLabel(): string;

    public function getDefaultSize(): WidgetSize;

    /** Whether the widget appears before a user customizes their dashboard. */
    public function isShownByDefault(): bool;

    /** Security attribute (usually a role) required to see the widget, or null for every admin user. */
    public function getRequiredPermission(): ?string;

    /** Only called for users who are allowed to see the widget. */
    public function render(): WidgetView;
}
