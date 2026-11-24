<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

final readonly class SavedView
{
    public const MAX_NAME_LENGTH = 60;

    /** @param array<string, mixed> $query */
    public function __construct(
        public string $id,
        public string $name,
        public array $query,
        public TableLayout $layout,
    ) {
    }

    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data)
            || !\is_string($data['id'] ?? null)
            || 1 !== preg_match('/^[A-Za-z0-9]{1,32}$/', $data['id'])
            || !\is_string($data['name'] ?? null)
            || '' === trim($data['name'])
        ) {
            return null;
        }

        return new self(
            $data['id'],
            mb_substr(trim($data['name']), 0, self::MAX_NAME_LENGTH),
            \is_array($data['query'] ?? null) ? $data['query'] : [],
            TableLayout::fromArray($data['layout'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'query' => $this->query, 'layout' => $this->layout->toArray()];
    }

    public function withLayout(TableLayout $layout): self
    {
        return new self($this->id, $this->name, $this->query, $layout);
    }
}
