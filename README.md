# EasyAdmin UX Bundle

[![CI](https://github.com/RunIO-dev/runio-easyadmin-ux/actions/workflows/ci.yml/badge.svg)](https://github.com/RunIO-dev/runio-easyadmin-ux/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/runio/easyadmin-ux)](https://packagist.org/packages/runio/easyadmin-ux)

An add-on for EasyAdmin 5. It does not fork or patch EasyAdmin.

- Four layouts that each user can switch between: Sidebar, Compact, Top navigation, and Focus.
- A Ctrl+K / Cmd+K command palette for jumping to any menu item.
- Faster navigation through link prefetching and native view transitions.
- Smart data tables: saved views, per-user column visibility and order, row density, sticky headers, and clearer bulk selection.

Prefetching and view transitions are progressive enhancements. Browsers without them use normal page loads.

## Installation

```bash
composer require runio/easyadmin-ux
php bin/console assets:install
```

Import the routes under the same firewall as your dashboard:

```yaml
# config/routes/runio_easyadmin_ux.yaml
runio_easyadmin_ux:
    resource: '@RunioEasyAdminUxBundle/config/routes.php'
    prefix: /admin
```

Create the preferences table:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Until the table exists, the admin keeps working with the default layout. Choosing a layout shows a warning instead of saving it.

## Configuration

```yaml
# config/packages/runio_easy_admin_ux.yaml
runio_easy_admin_ux:
    connection: default
    default_layout: sidebar
    layouts: [sidebar, compact, topbar, focus]
```

## Custom template overrides

If your app overrides EasyAdmin's layout or list template in `templates/bundles/EasyAdminBundle/`, extend this bundle's version so its features stay active:

```twig
{# templates/bundles/EasyAdminBundle/layout.html.twig #}
{% extends '@RunioEasyAdminUx/easyadmin/layout.html.twig' %}

{# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
{% extends '@RunioEasyAdminUx/easyadmin/crud/index.html.twig' %}
```

## Smart data tables

List pages get a toolbar automatically, with no changes to your CRUD controllers.

- **Columns:** each user can show, hide, and reorder columns. Hidden columns are removed on the server and never rendered. Columns you later add to a CRUD controller appear next to their configured neighbor.
- **Saved views:** a view stores the search, filters, sort, columns, and density. Each user can keep up to 20 views per table.
- **Density and sticky headers:** these apply from tablet width upward. Phones keep EasyAdmin's card layout.

Hiding a column is a display preference, not access control. Use EasyAdmin field permissions to restrict sensitive data.

## Prefetching

Sidebar and pagination links inside the current dashboard are prefetched when a user shows intent, such as hovering. URLs containing `logout` or `_switch_user` are excluded. Add `data-ux-no-prefetch` to any other link whose GET request has side effects, including a logout route inside the dashboard path that doesn't contain the word `logout`.

## Custom storage

Preferences are stored through `PreferenceStoreInterface`. Alias that interface to your own service to store them elsewhere.

## Inline editing and the edit drawer

List pages get a pencil on each editable cell, and row clicks or the Edit action open the record in a side drawer. Text, number, date, and simple select fields edit in the cell. Other fields open the drawer.

Both submit EasyAdmin's real edit form, so its permissions, CSRF protection, validation, and entity events still apply. To turn this off:

```yaml
runio_easy_admin_ux:
    inline_editing: false
```

The drawer loads the edit page in a same-origin iframe. If your application forbids framing, the Edit action falls back to the full page.

## Customizable dashboards

Render a widget grid in your dashboard template:

```twig
{% extends '@EasyAdmin/page/content.html.twig' %}

{% block main %}
    {{ runio_ux_dashboard() }}
{% endblock %}
```

Or render `@RunioEasyAdminUx/dashboard/page.html.twig` from your dashboard's `index()` action. Each user can choose Customize to reorder widgets (drag and drop, or the arrow buttons), resize them, remove them, and add hidden ones. Layouts are saved per user. Pass a name, as in `runio_ux_dashboard('sales')`, to keep a separate layout for another dashboard.

A widget is a service that implements `WidgetInterface`. With autoconfiguration it registers itself. Three base classes cover common cases:

```php
use Runio\EasyAdminUxBundle\Dashboard\MetricWidget;

final class OpenOrdersWidget extends MetricWidget
{
    public function __construct(private readonly OrderRepository $orders) {}

    public static function getDefaultPriority(): int { return 10; } // higher comes first

    public function getId(): string { return 'orders.open'; }
    public function getLabel(): string { return 'Open orders'; }
    public function getValue(): int { return $this->orders->countOpen(); }
    public function getRequiredPermission(): ?string { return 'ROLE_SALES'; } // optional
}
```

- `MetricWidget`: one headline value, with an optional hint and link.
- `ListWidget`: up to a handful of `ListItem`s, with an optional "View all" link.
- `BarChartWidget`: labelled values drawn as bars, with no charting library.

For anything else, extend `AbstractWidget` and return a `WidgetView` with your own Twig template. A widget the user may not see is never rendered, and the server ignores it when saving a layout. When a widget throws, it shows an error card in production and the exception in debug mode. Widgets render on every dashboard load, so keep their queries cheap.

## Development and tests

This repository holds the bundle at its root, plus a demo app in `demo/` and browser tests in `e2e/`. Neither of those ships with the Composer package.

Unit tests:

```bash
composer install
vendor/bin/phpunit
```

Demo app, which loads this bundle from the repository root through a Composer path repository:

```bash
docker compose -f demo-compose.yaml up -d --wait
cd demo
composer install
php bin/console doctrine:schema:update --force
php bin/console app:seed-demo
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8017 -t public
```

Open http://127.0.0.1:8017/admin and sign in as `ana` / `demo` (a manager) or `sam` / `demo`. These users exist only in the dev environment. Add `?_locale=ar` to any admin URL to try Arabic. Use more than one server worker: with a single worker, one idle browser connection blocks every other request.

Browser tests, which use Playwright against the demo app:

```bash
cd e2e
npm ci
npx playwright install chromium
npx playwright test
```

The browser tests delete all demo products and saved preferences, then seed them again with `php bin/console app:seed-demo --reset`. Do not run them against data you want to keep. They start the demo server when it is not already running.
