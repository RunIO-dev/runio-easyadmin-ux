<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Http;

use Runio\EasyAdminUxBundle\Http\SafeReturnPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SafeReturnPathTest extends TestCase
{
    public function testLocalPathWithQueryIsKept(): void
    {
        self::assertSame('/admin/product?page=2', SafeReturnPath::resolve('/admin/product?page=2', '/'));
    }

    #[DataProvider('unsafeCandidates')]
    public function testUnsafeCandidateFallsBack(string $candidate): void
    {
        self::assertSame('/fallback', SafeReturnPath::resolve($candidate, '/fallback'));
    }

    /** @return iterable<string, array{string}> */
    public static function unsafeCandidates(): iterable
    {
        yield 'empty' => [''];
        yield 'absolute url' => ['https://evil.example/'];
        yield 'protocol relative' => ['//evil.example/'];
        yield 'backslash host' => ['/\\evil.example/'];
        yield 'relative' => ['admin/product'];
        yield 'header injection' => ["/admin\r\nLocation: https://evil.example"];
        yield 'tab bypass' => ["/\t/evil.example"];
    }
}
