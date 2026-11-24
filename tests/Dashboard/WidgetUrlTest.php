<?php

declare(strict_types=1);

namespace Runio\EasyAdminUxBundle\Tests\Dashboard;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runio\EasyAdminUxBundle\Dashboard\ListItem;
use Runio\EasyAdminUxBundle\Dashboard\ListWidget;
use Runio\EasyAdminUxBundle\Dashboard\MetricWidget;
use Runio\EasyAdminUxBundle\Dashboard\WidgetUrl;

final class WidgetUrlTest extends TestCase
{
    /** @return iterable<array{?string, ?string}> */
    public static function urls(): iterable
    {
        yield 'path' => ['/admin/product?sort[stock]=ASC', '/admin/product?sort[stock]=ASC'];
        yield 'https' => ['https://example.com/a', 'https://example.com/a'];
        yield 'http, any case' => ['HTTP://example.com', 'HTTP://example.com'];
        yield 'mailto' => ['mailto:ops@example.com', 'mailto:ops@example.com'];
        yield 'relative' => ['report?id=4', 'report?id=4'];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'javascript with spaces and case' => [" \tJaVaScRiPt:alert(1)", null];
        yield 'data' => ['data:text/html,<script>alert(1)</script>', null];
        yield 'vbscript' => ['vbscript:msgbox', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('urls')]
    public function testOnlySafeLinksAreKept(?string $url, ?string $expected): void
    {
        self::assertSame($expected, WidgetUrl::sanitize($url));
    }

    public function testListAndMetricWidgetsDropUnsafeLinks(): void
    {
        $list = new class extends ListWidget {
            public function getId(): string { return 'list'; }
            public function getLabel(): string { return 'List'; }
            public function getItems(): iterable { yield new ListItem('Evil', 'javascript:alert(1)'); yield new ListItem('Good', '/ok'); }
            public function getMoreUrl(): ?string { return 'javascript:alert(2)'; }
        };
        $metric = new class extends MetricWidget {
            public function getId(): string { return 'metric'; }
            public function getLabel(): string { return 'Metric'; }
            public function getValue(): int { return 1; }
            public function getUrl(): ?string { return 'javascript:alert(3)'; }
        };

        $items = $list->render()->parameters['items'];
        self::assertNull($items[0]->url);
        self::assertSame('/ok', $items[1]->url);
        self::assertNull($list->render()->parameters['more_url']);
        self::assertNull($metric->render()->parameters['url']);
    }
}
