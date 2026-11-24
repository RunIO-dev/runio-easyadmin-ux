import { test, expect } from '@playwright/test';
import { BASE_URL, PHONE, noHorizontalOverflow, resetDemo, signIn, sql } from './support.mjs';

test.describe.configure({ mode: 'serial' });

const LIST = '/admin/product';
const DEFAULT_COLUMNS = 'name,sku,category,priceInCents,stock,active';
let ana;
let xssTriggered = false;

const headers = (page) => page.$$eval('table.datagrid thead th[data-column]', (cells) => cells.map((cell) => cell.dataset.column).join(','));
const rowColumns = (page) => page.$$eval('table.datagrid tbody tr:first-child td[data-column]', (cells) => cells.map((cell) => cell.dataset.column).join(','));
const rowCount = (page) => page.locator('table.datagrid tbody tr[data-id]').count();
const openMenu = async (page, label) => {
    await page.locator('.ux-table-button', { hasText: label }).click();
    await page.waitForTimeout(120);
};
const submit = async (page, locator) => {
    await Promise.all([page.waitForNavigation(), locator.click()]);
    await page.waitForTimeout(160);
};
const apply = (page) => submit(page, page.locator('.ux-columns-actions button', { hasText: 'Apply' }));
const saveView = async (page, name) => {
    await openMenu(page, 'Save view');
    await page.fill('#ux-view-name', name);
    await submit(page, page.locator('.ux-save-view button[type=submit]'));
};

test.beforeAll(async ({ browser }) => {
    resetDemo();
    sql(`insert into product (name, sku, price_in_cents, stock, active, category_id) values
        ('ZZTEMP one', 'ZZ-0001', 100, 1, 1, 1),
        ('ZZTEMP two', 'ZZ-0002', 100, 1, 1, 1),
        ('ZZSINGLE', 'ZZ-0003', 100, 1, 1, 1)`);
    ana = await signIn(browser, 'ana', { acceptDialogs: true });
    await ana.exposeFunction('uxXss', () => { xssTriggered = true; });
    await ana.goto(LIST);
});

test.afterAll(() => resetDemo());

test('columns can be hidden, reordered, dragged and reset', async ({ browser }) => {
    await expect(ana.locator('.ux-table-toolbar')).toBeVisible();
    expect(await headers(ana)).toBe(DEFAULT_COLUMNS);

    await openMenu(ana, 'Columns');
    await ana.locator('.ux-columns li[data-column=sku] input[type=checkbox]').uncheck();
    await ana.locator('.ux-columns li[data-column=stock] [data-ux-move=up]').click();
    await ana.locator('.ux-columns li[data-column=stock] [data-ux-move=up]').click();
    await apply(ana);
    const expected = 'name,stock,category,priceInCents,active';
    expect(await headers(ana), 'header').toBe(expected);
    expect(await rowColumns(ana), 'body cells match header').toBe(expected);
    expect(await ana.content(), 'hidden column is not rendered').not.toContain('MG-0001');

    await ana.reload();
    expect(await headers(ana), 'survives reload').toBe(expected);
    const sam = await signIn(browser, 'sam');
    await sam.goto(LIST);
    expect(await headers(sam), 'other user keeps defaults').toBe(DEFAULT_COLUMNS);
    expect(sam.errors).toEqual([]);
    await sam.context().close();

    await openMenu(ana, 'Columns');
    await ana.locator('.ux-columns li[data-column=active] .ux-grip')
        .dragTo(ana.locator('.ux-columns li[data-column=name]'), { targetPosition: { x: 40, y: 2 } });
    await apply(ana);
    expect((await headers(ana)).split(',')[0], 'drag and drop reorders').toBe('active');

    await openMenu(ana, 'Columns');
    for (const box of await ana.locator('.ux-columns input[type=checkbox]').all()) {
        if (await box.isChecked()) await box.click();
    }
    await expect(ana.locator('.ux-columns input[type=checkbox]:checked'), 'last column cannot be hidden').toHaveCount(1);
    await ana.keyboard.press('Escape');
});

test('density changes row height', async () => {
    await ana.goto(LIST);
    const rowHeight = () => ana.locator('table.datagrid tbody tr[data-id]').first().evaluate((row) => row.getBoundingClientRect().height);
    const comfortable = await rowHeight();
    await submit(ana, ana.locator('.ux-density button[value=compact]'));
    expect(await rowHeight()).toBeLessThan(comfortable);
    await submit(ana, ana.locator('.ux-density button[value=spacious]'));
    expect(await rowHeight()).toBeGreaterThan(comfortable);
    await expect(ana.locator('.ux-density button[value=spacious]')).toHaveAttribute('aria-pressed', 'true');
    await submit(ana, ana.locator('.ux-density button[value=comfortable]'));

    await openMenu(ana, 'Columns');
    await submit(ana, ana.locator('.ux-columns-actions button', { hasText: 'Reset' }));
    expect(await headers(ana), 'reset restores columns').toBe(DEFAULT_COLUMNS);
});

test('saved views keep filters, sort and columns', async () => {
    await ana.goto(`${LIST}?${new URLSearchParams({ 'filters[stock][comparison]': '<', 'filters[stock][value]': '20', 'sort[stock]': 'ASC' })}`);
    const filteredRows = await rowCount(ana);
    await openMenu(ana, 'Columns');
    await ana.locator('.ux-columns li[data-column=category] input[type=checkbox]').uncheck();
    await apply(ana);
    await saveView(ana, 'Low stock');
    expect(ana.url()).toContain('ux_view=');
    await expect(ana.locator('.ux-view.is-active', { hasText: 'Low stock' })).toBeVisible();
    const viewUrl = ana.url();

    await openMenu(ana, 'Columns');
    await ana.locator('.ux-columns li[data-column=active] input[type=checkbox]').uncheck();
    await apply(ana);
    expect(await headers(ana), 'editing a view changes the view').not.toContain('active');
    expect(ana.url(), 'view stays open after edit').toContain('ux_view=');

    await submit(ana, ana.locator('.ux-views > a', { hasText: 'All records' }));
    expect(ana.url(), 'All records clears filters').not.toContain('filters');
    expect(await headers(ana), 'base layout untouched').toContain('active');

    await submit(ana, ana.locator('.ux-view a', { hasText: 'Low stock' }));
    expect(await rowCount(ana), 'restores filter').toBe(filteredRows);
    expect(decodeURIComponent(ana.url()), 'restores sort').toContain('sort[stock]=ASC');
    expect(await headers(ana), 'restores columns').not.toMatch(/category|active/);

    await ana.goto(viewUrl);
    await submit(ana, ana.locator('table.datagrid thead th[data-column=name] a'));
    expect(ana.url(), 'sorting keeps the view').toContain('ux_view=');
    expect(await headers(ana)).not.toContain('active');

    await ana.goto(viewUrl);
    await ana.click('.action-filters-button');
    await ana.waitForSelector('#modal-filters form', { state: 'attached' });
    await expect(ana.locator('#modal-filters input[name=ux_view]'), 'filter form keeps the view').toHaveCount(1);
    await ana.waitForTimeout(450);
    expect(await ana.evaluate(() => {
        const dialog = document.querySelector('#modal-filters .modal-content');
        const box = dialog.getBoundingClientRect();
        return dialog.contains(document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2));
    }), 'filter modal is clickable').toBe(true);
    await ana.keyboard.press('Escape');
    await ana.waitForTimeout(350);
});

test('pagination works with the toolbar', async () => {
    await ana.goto(LIST);
    await submit(ana, ana.locator('.page-link', { hasText: '2' }).first());
    expect(ana.url()).toContain('page=2');
});

test('EasyAdmin delete confirmation still works', async () => {
    await ana.goto(`${LIST}?query=ZZSINGLE`);
    expect(await rowCount(ana)).toBe(1);
    await ana.locator('table.datagrid tbody tr[data-id] .dropdown-toggle').first().click();
    await ana.locator('table.datagrid tbody tr .dropdown-menu.show .action-delete').click();
    await ana.waitForSelector('#modal-action-confirmation-button', { state: 'visible' });
    await ana.waitForTimeout(350);
    await Promise.all([ana.waitForNavigation(), ana.click('#modal-action-confirmation-button')]);
    await ana.goto(`${LIST}?query=ZZSINGLE`);
    expect(await rowCount(ana)).toBe(0);
});

test('view names are escaped and active views can be deleted', async () => {
    await ana.goto(LIST);
    await saveView(ana, '<img src=x onerror=uxXss()>');
    await ana.waitForTimeout(250);
    expect(xssTriggered).toBe(false);
    await expect(ana.locator('.ux-views img')).toHaveCount(0);
    await submit(ana, ana.locator('.ux-view.is-active form button'));
    expect(ana.url()).not.toContain('ux_view=');
    await expect(ana.locator('.ux-view', { hasText: 'onerror' })).toHaveCount(0);
});

test('header stays visible while scrolling', async () => {
    await ana.setViewportSize({ width: 1440, height: 560 });
    await ana.goto(LIST);
    const stuck = await ana.evaluate(() => {
        const scroll = document.querySelector('.datagrid-scroll');
        scroll.scrollTop = 120;
        const th = document.querySelector('table.datagrid thead th[data-column]');
        return { scrolled: scroll.scrollTop, offset: Math.abs(th.getBoundingClientRect().top - scroll.getBoundingClientRect().top) };
    });
    expect(stuck.scrolled).toBeGreaterThan(0);
    expect(stuck.offset).toBeLessThanOrEqual(1);
    await ana.setViewportSize({ width: 1440, height: 900 });
});

test('bulk selection supports ranges and EasyAdmin batch delete', async () => {
    await ana.goto(LIST);
    const boxes = ana.locator('.form-batch-checkbox');
    await boxes.nth(0).click();
    await boxes.nth(3).click({ modifiers: ['Shift'] });
    await expect(ana.locator('.ux-selection-count')).toHaveText('4 selected');
    await ana.click('.ux-selection-clear');
    await expect(ana.locator('.form-batch-checkbox:checked')).toHaveCount(0);
    await ana.waitForTimeout(300);

    await ana.goto(`${LIST}?query=ZZTEMP`);
    expect(await rowCount(ana)).toBe(2);
    await ana.locator('.form-batch-checkbox-all').check();
    await ana.waitForTimeout(320);
    await ana.locator('.batch-actions .action-batchDelete, .batch-actions [data-action-name=batchDelete]').first().click();
    await ana.waitForSelector('#modal-batch-action-button', { state: 'visible' });
    await ana.waitForTimeout(350);
    await Promise.all([ana.waitForNavigation(), ana.click('#modal-batch-action-button')]);
    await ana.goto(`${LIST}?query=ZZTEMP`);
    expect(await rowCount(ana)).toBe(0);
    await expect(ana.locator('.ux-table-toolbar'), 'empty results page renders').toBeVisible();
});

test('table endpoint rejects bad requests', async () => {
    await ana.goto(LIST);
    const token = await ana.getAttribute('.ux-density input[name=_token]', 'value');
    const crud = await ana.getAttribute('.ux-density input[name=crud]', 'value');
    const post = (body, manual = true) => ana.evaluate(async ({ url, body, manual }) => {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(body),
            redirect: manual ? 'manual' : 'follow',
        });
        return manual ? response.status : new URL(response.url).origin;
    }, { url: `${BASE_URL}/admin/_ux/table`, body, manual });

    expect(await post({ crud, _token: 'wrong', action: 'density', density: 'compact' }), 'bad CSRF').toBe(403);
    expect(await post({ crud: 'App\\Other', _token: token, action: 'density', density: 'compact' }), 'token bound to its table').toBe(403);
    expect(await post({ crud, _token: token, action: 'nope' }), 'unknown action').toBe(400);
    expect(await post({ crud, _token: token, action: 'view_save', name: '   ' }), 'empty view name').toBe(400);
    expect(
        await post({ crud, _token: token, action: 'density', density: 'comfortable', _return: 'https://evil.example/' }, false),
        'return URL cannot leave the site',
    ).toBe(BASE_URL);
});

test('malformed or unknown views fall back safely', async () => {
    await ana.goto(`${LIST}?ux_view[]=x`);
    await expect(ana.locator('table.datagrid')).toBeVisible();
    await ana.goto(LIST);
    const base = await headers(ana);
    await ana.goto(`${LIST}?ux_view=doesnotexist`);
    expect(await headers(ana)).toBe(base);
});

test('Enter in the column menu applies instead of resetting', async () => {
    await ana.goto(LIST);
    const before = (await headers(ana)).split(',');
    await openMenu(ana, 'Columns');
    await ana.locator('.ux-columns li[data-column=stock] input[type=checkbox]').focus();
    await ana.keyboard.press('Space');
    await Promise.all([ana.waitForNavigation(), ana.keyboard.press('Enter')]);
    await ana.waitForTimeout(160);
    expect(await headers(ana)).toBe(before.filter((column) => 'stock' !== column).join(','));
    await openMenu(ana, 'Columns');
    await submit(ana, ana.locator('.ux-columns-actions button', { hasText: 'Reset' }));
    expect(await headers(ana)).toBe(DEFAULT_COLUMNS);
});

test('views keep extra list parameters', async () => {
    await ana.goto(`${LIST}?scope=archived`);
    expect(await ana.getAttribute('.ux-views > a', 'href'), 'All records keeps context').toContain('scope=archived');
    await saveView(ana, 'Scoped');
    expect(ana.url()).toContain('scope=archived');
    expect(ana.url()).toContain('ux_view=');
    await ana.goto(LIST);
    expect(await ana.locator('.ux-view a', { hasText: 'Scoped' }).getAttribute('href')).toContain('scope=archived');
    await submit(ana, ana.locator('.ux-view a', { hasText: 'Scoped' }));
    await submit(ana, ana.locator('.ux-view.is-active form button'));
    expect(ana.url()).toContain('scope=archived');
    expect(ana.url()).not.toContain('ux_view=');
});

test('views that cannot be stored show a warning instead of an error', async () => {
    const attempt = async (url) => {
        await ana.goto(url);
        await openMenu(ana, 'Save view');
        await ana.fill('#ux-view-name', 'Should not save');
        const [response] = await Promise.all([ana.waitForNavigation(), ana.locator('.ux-save-view button[type=submit]').click()]);
        await ana.waitForTimeout(120);
        const text = await ana.locator('body').innerText();
        return {
            status: response.status(),
            warned: text.includes('could not be saved') || text.includes('too many filters'),
            saved: await ana.locator('.ux-view', { hasText: 'Should not save' }).count(),
        };
    };
    expect(await attempt(`${LIST}?query=%FF`), 'invalid UTF-8').toEqual({ status: 200, warned: true, saved: 0 });
    expect(await attempt(`${LIST}?scope=${'a'.repeat(4200)}`), 'oversized view').toEqual({ status: 200, warned: true, saved: 0 });
});

test('selection state is consistent after going back', async () => {
    await ana.goto(LIST);
    await ana.locator('.form-batch-checkbox').nth(1).check();
    await ana.goto('/admin/category');
    await ana.goBack();
    await ana.waitForTimeout(250);
    const state = await ana.evaluate(() => ({
        checked: document.querySelectorAll('.form-batch-checkbox:checked').length,
        count: document.querySelector('.ux-selection-count')?.textContent ?? '',
        inert: document.querySelector('.batch-actions')?.inert,
    }));
    const consistent = (0 === state.checked && '' === state.count && state.inert)
        || (state.checked > 0 && state.count === `${state.checked} selected` && !state.inert);
    expect(consistent, JSON.stringify(state)).toBe(true);
});

test('phone width has no horizontal overflow', async ({ browser }) => {
    const phone = await signIn(browser, 'sam', { viewport: PHONE });
    await phone.goto(LIST);
    expect(await noHorizontalOverflow(phone)).toBe(true);
    expect(phone.errors).toEqual([]);
    await phone.context().close();
});

test('no JavaScript errors', () => {
    expect(ana.errors).toEqual([]);
});
