<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

final readonly class TablePreferences
{
    public const MAX_VIEWS = 20;

    public TableLayout $layout;

    /** @param list<SavedView> $views */
    public function __construct(?TableLayout $layout = null, public array $views = [])
    {
        $this->layout = $layout ?? new TableLayout();
    }

    public static function fromJson(?string $json): self
    {
        if (null === $json || '' === $json) {
            return new self();
        }

        $data = json_decode($json, true);
        if (!\is_array($data)) {
            return new self();
        }

        $views = array_map(SavedView::fromArray(...), \is_array($data['views'] ?? null) ? array_values($data['views']) : []);

        return new self(
            TableLayout::fromArray($data['layout'] ?? null),
            \array_slice(array_values(array_filter($views)), 0, self::MAX_VIEWS),
        );
    }

    public function toJson(): string
    {
        return json_encode([
            'layout' => $this->layout->toArray(),
            'views' => array_map(static fn (SavedView $view): array => $view->toArray(), $this->views),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);
    }

    public function view(?string $id): ?SavedView
    {
        foreach ($this->views as $view) {
            if ($view->id === $id) {
                return $view;
            }
        }

        return null;
    }

    /** Adds a view, or replaces the existing view with the same name while keeping its id. */
    public function withView(SavedView $view): self
    {
        $views = $this->views;
        foreach ($views as $index => $existing) {
            if (mb_strtolower($existing->name) === mb_strtolower($view->name)) {
                $views[$index] = new SavedView($existing->id, $view->name, $view->query, $view->layout);

                return new self($this->layout, $views);
            }
        }

        if (\count($views) >= self::MAX_VIEWS) {
            throw new TooManySavedViewsException(\sprintf('A table can have at most %d saved views.', self::MAX_VIEWS));
        }

        $views[] = $view;

        return new self($this->layout, $views);
    }

    public function withoutView(string $id): self
    {
        return new self(
            $this->layout,
            array_values(array_filter($this->views, static fn (SavedView $view): bool => $view->id !== $id)),
        );
    }

    /** Updates the given saved view, or the table's base layout when no view is active. */
    public function withLayout(TableLayout $layout, ?string $viewId): self
    {
        if (null === $this->view($viewId)) {
            return new self($layout, $this->views);
        }

        return new self($this->layout, array_map(
            static fn (SavedView $view): SavedView => $view->id === $viewId ? $view->withLayout($layout) : $view,
            $this->views,
        ));
    }

    public function layoutFor(?string $viewId): TableLayout
    {
        return $this->view($viewId)?->layout ?? $this->layout;
    }
}
