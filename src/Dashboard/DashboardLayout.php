<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/**
 * A user's arrangement of one dashboard. It also remembers which widgets existed when it was
 * saved, so widgets an application adds later still appear, while removed ones stay removed.
 */
final class DashboardLayout
{
    public const MAX_WIDGETS = 50;

    /**
     * @param list<WidgetPlacement> $placements
     * @param list<string>          $known
     */
    public function __construct(
        public readonly array $placements = [],
        public readonly array $known = [],
        public readonly bool $customized = false,
    ) {
    }

    /**
     * @param array<mixed>                   $requested Items shaped like {id: string, size: string}
     * @param array<string, WidgetInterface> $available
     * @param list<mixed>|null               $shown     Widget ids the page offered when it was loaded
     */
    public static function customize(array $requested, array $available, ?array $shown = null): self
    {
        $placements = [];
        foreach ($requested as $item) {
            $id = \is_array($item) ? ($item['id'] ?? null) : null;
            if (!\is_string($id) || !isset($available[$id]) || isset($placements[$id]) || \count($placements) >= self::MAX_WIDGETS) {
                continue;
            }

            $size = \is_string($item['size'] ?? null) ? WidgetSize::tryFrom($item['size']) : null;
            $placements[$id] = new WidgetPlacement($id, $size ?? $available[$id]->getDefaultSize());
        }

        // A widget deployed after the page was loaded wasn't offered yet, so it still counts as new.
        $known = array_keys($available);
        if (null !== $shown) {
            $known = array_values(array_intersect($known, array_filter($shown, 'is_string')));
        }

        return new self(array_values($placements), $known, true);
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        if (true !== ($data['customized'] ?? false)) {
            return new self();
        }

        $placements = [];
        foreach (\is_array($data['placements'] ?? null) ? $data['placements'] : [] as $item) {
            $id = \is_array($item) ? ($item['id'] ?? null) : null;
            $size = \is_array($item) && \is_string($item['size'] ?? null) ? WidgetSize::tryFrom($item['size']) : null;
            if (\is_string($id) && 1 === preg_match(WidgetRegistry::ID_PATTERN, $id) && null !== $size && !isset($placements[$id])) {
                $placements[$id] = new WidgetPlacement($id, $size);
            }
        }

        $known = array_values(array_filter(
            \is_array($data['known'] ?? null) ? $data['known'] : [],
            static fn (mixed $id): bool => \is_string($id) && 1 === preg_match(WidgetRegistry::ID_PATTERN, $id),
        ));

        return new self(\array_slice(array_values($placements), 0, self::MAX_WIDGETS), $known, true);
    }

    /** @return array{placements: list<array{id: string, size: string}>, known: list<string>, customized: bool} */
    public function toArray(): array
    {
        return [
            'placements' => array_map(static fn (WidgetPlacement $p): array => ['id' => $p->id, 'size' => $p->size->value], $this->placements),
            'known' => $this->known,
            'customized' => $this->customized,
        ];
    }

    /**
     * @param array<string, WidgetInterface> $available
     *
     * @return list<WidgetPlacement>
     */
    public function resolve(array $available): array
    {
        $isNewDefault = fn (WidgetInterface $widget): bool => $widget->isShownByDefault()
            && (!$this->customized || !\in_array($widget->getId(), $this->known, true));

        $placements = array_values(array_filter($this->placements, static fn (WidgetPlacement $p): bool => isset($available[$p->id])));
        foreach (array_filter($available, $isNewDefault) as $id => $widget) {
            $placements[] = new WidgetPlacement($id, $widget->getDefaultSize());
        }

        return $placements;
    }

    /**
     * @param array<string, WidgetInterface> $available
     *
     * @return array<string, WidgetInterface> Widgets the user could add
     */
    public function hidden(array $available): array
    {
        $placed = array_map(static fn (WidgetPlacement $p): string => $p->id, $this->resolve($available));

        return array_diff_key($available, array_flip($placed));
    }
}
