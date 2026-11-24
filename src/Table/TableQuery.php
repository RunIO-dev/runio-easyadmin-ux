<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Table;

final class TableQuery
{
    public const MAX_LENGTH = 4096;
    public const VIEW_PARAMETER = 'ux_view';

    private const LIST_STATE = ['query', 'sort', 'filters'];
    private const TRANSIENT = ['page', self::VIEW_PARAMETER, 'referrer', 'ux_drawer'];

    /**
     * Keeps what a saved view must restore: search, sort, filters, and any application context
     * such as parameters set by a menu item to scope the list.
     *
     * @return array<string, mixed>
     *
     * @throws InvalidTableStateException
     */
    public static function fromQueryString(string $queryString): array
    {
        if (\strlen($queryString) > self::MAX_LENGTH) {
            throw new InvalidTableStateException('This view has too many filters to be saved.');
        }

        parse_str($queryString, $parameters);
        if (!mb_check_encoding($parameters, 'UTF-8')) {
            throw new InvalidTableStateException('This view contains invalid characters and could not be saved.');
        }

        $query = self::context($parameters);

        if (\is_string($parameters['query'] ?? null) && '' !== $parameters['query']) {
            $query['query'] = $parameters['query'];
        }

        if (\is_array($parameters['sort'] ?? null)) {
            $sort = array_filter(
                $parameters['sort'],
                static fn (mixed $direction, int|string $property): bool => \in_array($direction, ['ASC', 'DESC'], true)
                    && 1 === preg_match('/^[\w.]+$/', (string) $property),
                \ARRAY_FILTER_USE_BOTH,
            );
            if ([] !== $sort) {
                $query['sort'] = $sort;
            }
        }

        if (\is_array($parameters['filters'] ?? null) && [] !== $parameters['filters']) {
            $query['filters'] = $parameters['filters'];
        }

        return $query;
    }

    /**
     * @param array<array-key, mixed> $parameters
     *
     * @return array<array-key, mixed> the parameters that aren't list state or transient
     */
    public static function context(array $parameters): array
    {
        return array_diff_key($parameters, array_flip([...self::LIST_STATE, ...self::TRANSIENT]));
    }

    /** @param array<string, mixed> $query */
    public static function url(string $path, array $query, ?string $viewId): string
    {
        if (null !== $viewId) {
            $query[self::VIEW_PARAMETER] = $viewId;
        }

        return [] === $query ? $path : $path.'?'.http_build_query($query);
    }
}
