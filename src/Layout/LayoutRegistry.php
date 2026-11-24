<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Layout;

final class LayoutRegistry
{
    public const BUILT_IN = [
        'sidebar' => 'Sidebar',
        'compact' => 'Compact',
        'topbar' => 'Top navigation',
        'focus' => 'Focus',
    ];

    /** @var array<string, LayoutDefinition> */
    private array $layouts = [];

    /** @param iterable<LayoutDefinition> $layouts */
    public function __construct(iterable $layouts, private readonly string $defaultId)
    {
        foreach ($layouts as $layout) {
            $this->layouts[$layout->id] = $layout;
        }

        if (!isset($this->layouts[$defaultId])) {
            throw UnknownLayoutException::forId($defaultId);
        }
    }

    /** @param list<string> $enabledIds */
    public static function fromBuiltIn(array $enabledIds, string $defaultId): self
    {
        $layouts = [];
        foreach ($enabledIds as $id) {
            if (!isset(self::BUILT_IN[$id])) {
                throw UnknownLayoutException::forId($id);
            }
            $layouts[] = new LayoutDefinition($id, self::BUILT_IN[$id], $id);
        }

        return new self($layouts, $defaultId);
    }

    public function has(string $id): bool
    {
        return isset($this->layouts[$id]);
    }

    public function get(string $id): LayoutDefinition
    {
        return $this->layouts[$id] ?? throw UnknownLayoutException::forId($id);
    }

    public function getDefault(): LayoutDefinition
    {
        return $this->layouts[$this->defaultId];
    }

    /** @return list<LayoutDefinition> */
    public function all(): array
    {
        return array_values($this->layouts);
    }
}
