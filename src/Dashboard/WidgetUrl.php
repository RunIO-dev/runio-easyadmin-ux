<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Dashboard;

/** Keeps widget links to web pages and email, so data such as "javascript:..." can't run script. */
final class WidgetUrl
{
    public static function sanitize(?string $url): ?string
    {
        if (null === $url) {
            return null;
        }

        // Browsers ignore leading whitespace and control characters before a scheme.
        $normalized = strtolower(ltrim($url, "\x00..\x20"));
        if (1 !== preg_match('/^([a-z][a-z0-9+.-]*):/', $normalized, $match)) {
            return $url;
        }

        return \in_array($match[1], ['http', 'https', 'mailto'], true) ? $url : null;
    }
}
