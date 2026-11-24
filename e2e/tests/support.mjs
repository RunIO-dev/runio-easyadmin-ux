import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const DEMO_DIR = fileURLToPath(new URL('../../demo/', import.meta.url));
export const BASE_URL = process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8017';
export const DESKTOP = { width: 1440, height: 900 };
export const PHONE = { width: 390, height: 800 };

export const PASSWORD = 'demo'; // dev-only demo password, see demo/config/packages/security.yaml

const symfony = (...args) =>
    execFileSync('php', ['bin/console', '--no-ansi', ...args], { cwd: DEMO_DIR, encoding: 'utf8', timeout: 60_000 });

/** Puts the demo back to the 13 seeded products, with IDs from 1 and no saved preferences. */
export const resetDemo = () => symfony('app:seed-demo', '--reset');

export const sql = (query) => symfony('dbal:run-sql', query);

/** Returns the single value of a one-row, one-column query, read from the console table output. */
export const sqlValue = (query) => {
    const lines = sql(query).split('\n').map((line) => line.trim()).filter((line) => line && !/^[-\s]+$/.test(line));
    if (2 !== lines.length) throw new Error(`Expected one row from "${query}", got:\n${lines.join('\n')}`);
    return lines[1];
};

const PRODUCT_COLUMNS = ['name', 'sku', 'price_in_cents', 'stock', 'active', 'category_id'];
export const productValue = (column, id = 1) => {
    if (!PRODUCT_COLUMNS.includes(column)) throw new Error(`Unknown product column ${column}`);
    return sqlValue(`select ${column} as v from product where id = ${Number(id)}`);
};

export const tableExists = (table) =>
    '1' === sqlValue(`select count(*) as v from information_schema.tables where table_schema = database() and table_name = '${table.replace(/[^a-z0-9_]/g, '')}'`);

// Expected noise: 4xx requests are asserted by status where they matter (5xx responses are recorded
// separately below), and Chromium logs
// an aborted view transition when a page reloads while one is still running.
const IGNORED_CONSOLE_ERRORS = ['Failed to load resource', 'Transition was aborted'];

/**
 * Signs in a demo user in a fresh browser context.
 * The page gets an `errors` array that collects uncaught errors and console errors.
 */
export async function signIn(browser, user, { viewport = DESKTOP, acceptDialogs = false } = {}) {
    const context = await browser.newContext({ viewport, baseURL: BASE_URL });
    const page = await context.newPage();
    page.errors = [];
    page.on('pageerror', (error) => page.errors.push(error.message));
    page.on('console', (message) => {
        if ('error' === message.type() && !IGNORED_CONSOLE_ERRORS.some((text) => message.text().includes(text))) {
            page.errors.push(message.text());
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 500) page.errors.push(`HTTP ${response.status()} ${response.url()}`);
    });
    if (acceptDialogs) page.on('dialog', (dialog) => dialog.accept());

    await page.goto('/login');
    await page.fill('#username', user);
    await page.fill('#password', PASSWORD);
    await Promise.all([page.waitForURL('**/admin'), page.click('button[type=submit]')]);

    return page;
}

export const noHorizontalOverflow = (page) =>
    page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1);
