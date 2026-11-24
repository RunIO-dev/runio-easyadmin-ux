<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class WidgetRegistry
{
    // Starts with a letter: PHP would turn an all-digit id used as an array key into an integer.
    public const ID_PATTERN = '/^[a-z][a-z0-9_.-]{0,63}$/';

    /** @var array<string, WidgetInterface>|null */
    private ?array $widgets = null;

    /** @param iterable<WidgetInterface> $widgets */
    public function __construct(
        private readonly iterable $source,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /** @return array<string, WidgetInterface> */
    public function all(): array
    {
        if (null !== $this->widgets) {
            return $this->widgets;
        }

        $widgets = [];
        foreach ($this->source as $widget) {
            $id = $widget->getId();
            if (1 !== preg_match(self::ID_PATTERN, $id)) {
                throw new \LogicException(\sprintf('The dashboard widget id "%s" of %s must start with a lowercase letter and may only contain lowercase letters, digits, ".", "_" and "-".', $id, $widget::class));
            }
            if (isset($widgets[$id])) {
                throw new \LogicException(\sprintf('Two dashboard widgets use the id "%s": %s and %s.', $id, $widgets[$id]::class, $widget::class));
            }
            $widgets[$id] = $widget;
        }

        return $this->widgets = $widgets;
    }

    /** @return array<string, WidgetInterface> Widgets the current user may see */
    public function available(): array
    {
        return array_filter($this->all(), function (WidgetInterface $widget): bool {
            $permission = $widget->getRequiredPermission();

            return null === $permission || $this->authorizationChecker->isGranted($permission);
        });
    }
}
