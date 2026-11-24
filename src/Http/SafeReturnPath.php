<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Http;

final class SafeReturnPath
{
    /** Accepts only same-site absolute paths, so a submitted value can't cause an open redirect. */
    public static function resolve(string $candidate, string $fallback): string
    {
        $isLocal = str_starts_with($candidate, '/')
            && !str_starts_with($candidate, '//')
            && !str_contains($candidate, '\\')
            && 1 !== preg_match('/[\x00-\x1F\x7F]/', $candidate);

        return $isLocal ? $candidate : $fallback;
    }
}
