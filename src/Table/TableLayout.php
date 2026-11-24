<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

final readonly class TableLayout
{
    public const DENSITY_COMPACT = 'compact';
    public const DENSITY_COMFORTABLE = 'comfortable';
    public const DENSITY_SPACIOUS = 'spacious';
    public const DENSITIES = [self::DENSITY_COMPACT, self::DENSITY_COMFORTABLE, self::DENSITY_SPACIOUS];

    private const MAX_COLUMNS = 100;
    private const MAX_COLUMN_LENGTH = 100;

    /**
     * @param list<string> $order
     * @param list<string> $hidden
     */
    public function __construct(
        public array $order = [],
        public array $hidden = [],
        public string $density = self::DENSITY_COMFORTABLE,
    ) {
    }

    /**
     * @param array<mixed> $order
     * @param array<mixed> $hidden
     */
    public static function create(array $order = [], array $hidden = [], string $density = self::DENSITY_COMFORTABLE): self
    {
        return new self(
            self::sanitizeColumns($order),
            self::sanitizeColumns($hidden),
            \in_array($density, self::DENSITIES, true) ? $density : self::DENSITY_COMFORTABLE,
        );
    }

    public static function fromArray(mixed $data): self
    {
        if (!\is_array($data)) {
            return new self();
        }

        return self::create(
            \is_array($data['order'] ?? null) ? $data['order'] : [],
            \is_array($data['hidden'] ?? null) ? $data['hidden'] : [],
            \is_string($data['density'] ?? null) ? $data['density'] : self::DENSITY_COMFORTABLE,
        );
    }

    /** @return array{order: list<string>, hidden: list<string>, density: string} */
    public function toArray(): array
    {
        return ['order' => $this->order, 'hidden' => $this->hidden, 'density' => $this->density];
    }

    public function withDensity(string $density): self
    {
        return self::create($this->order, $this->hidden, $density);
    }

    /**
     * All available columns in display order. A column missing from the saved order is placed
     * right after the column that precedes it in the CRUD configuration.
     *
     * @param list<string> $properties
     *
     * @return list<string>
     */
    public function ordered(array $properties): array
    {
        $result = array_values(array_intersect($this->order, $properties));

        foreach ($properties as $index => $property) {
            if (\in_array($property, $result, true)) {
                continue;
            }

            $position = 0 === $index ? 0 : (int) array_search($properties[$index - 1], $result, true) + 1;
            array_splice($result, $position, 0, [$property]);
        }

        return $result;
    }

    /**
     * @param list<string> $properties
     *
     * @return list<string>
     */
    public function visible(array $properties): array
    {
        $ordered = $this->ordered($properties);
        $visible = array_values(array_diff($ordered, $this->hidden));

        return [] === $visible ? $ordered : $visible;
    }

    /**
     * @param array<mixed> $columns
     *
     * @return list<string>
     */
    private static function sanitizeColumns(array $columns): array
    {
        $valid = array_filter(
            $columns,
            static fn (mixed $column): bool => \is_string($column)
                && '' !== $column
                && \strlen($column) <= self::MAX_COLUMN_LENGTH
                && mb_check_encoding($column, 'UTF-8'),
        );

        return \array_slice(array_values(array_unique($valid)), 0, self::MAX_COLUMNS);
    }
}
