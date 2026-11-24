import { test, expect } from '@playwright/test';
import { resetDemo, signIn, noHorizontalOverflow } from './support.mjs';

test.describe.configure({ mode: 'serial' });

const DEFAULTS = ['products.count:small', 'products.inactive:small', 'inventory.value:small', 'products.low_stock:medium', 'stock.by_category:medium'];
let ana;
let sam;

const widgets = (page) => page.locator('.ux-widget').evaluateAll((all) => all.map((w) => `${w.dataset.widget}:${w.dataset.size}`));
const addWidget = async (page, id) => {
    await page.click('[data-ux-dashboard-add]');
    await page.waitForSelector('[data-ux-dashboard-add-menu].show');
    await page.waitForTimeout(350);
    await page.click(`[data-ux-add="${id}"]`);
    await page.waitForTimeout(150);
};
/** Clicks a button that asks for confirmation, accepts, and waits for the reload. */
const confirmAndReload = async (page, selector) => {
    page.once('dialog', (dialog) => dialog.accept());
    await Promise.all([page.waitForNavigation(), page.click(selector)]);
};

test.beforeAll(async ({ browser }) => {
    resetDemo();
    ana = await signIn(browser, 'ana');
});

test.afterAll(() => resetDemo());

test('shows the default widgets', async () => {
    expect(await widgets(ana)).toEqual(DEFAULTS);
    await expect(ana.locator('[data-widget=shortcuts]'), 'optional widget hidden').toHaveCount(0);
    await expect(ana.locator('.ux-widget-controls').first(), 'controls hidden outside edit mode').toBeHidden();
});

test('customize, save and cancel', async () => {
    await ana.click('[data-ux-dashboard-customize]');
    await ana.selectOption('[data-widget="products.low_stock"] [data-ux-widget-size]', 'full');
    await ana.click('[data-widget="inventory.value"] [data-ux-widget-move="-1"]');
    await expect(ana.locator('[data-ux-dashboard-status]'), 'move is announced').toContainText('position 2');
    await ana.click('[data-widget="products.inactive"] [data-ux-widget-remove]');
    await addWidget(ana, 'shortcuts');
    await addWidget(ana, 'products.inactive');
    await expect(ana.locator('[data-widget="products.inactive"] .ux-metric-value'), 're-added widget keeps its content').toHaveCount(1);
    await expect(ana.locator('[data-ux-dashboard-add]'), 'add disabled when none are left').toBeDisabled();
    await ana.click('[data-widget="products.inactive"] [data-ux-widget-remove]');
    await ana.locator('[data-widget="shortcuts"] h2').dragTo(ana.locator('[data-widget="products.count"] .ux-metric-value'));
    await ana.waitForTimeout(800);
    await Promise.all([ana.waitForNavigation(), ana.click('.ux-dashboard-editbar button[value=save]')]);

    const saved = ['shortcuts:medium', 'products.count:small', 'inventory.value:small', 'products.low_stock:full', 'stock.by_category:medium'];
    expect(await widgets(ana)).toEqual(saved);
    await expect(ana.locator('[data-widget=shortcuts] a')).toHaveCount(3);

    await ana.click('[data-ux-dashboard-customize]');
    await ana.click('[data-widget="products.count"] [data-ux-widget-remove]');
    await confirmAndReload(ana, '[data-ux-dashboard-cancel]');
    expect(await widgets(ana), 'cancel discards').toEqual(saved);
});

test('widgets respect permissions, even in forged saves', async ({ browser }) => {
    sam = await signIn(browser, 'sam');
    expect(await widgets(sam), 'sam keeps own layout, without the manager widget').toHaveLength(4);
    expect(await sam.content()).not.toContain('inventory.value');

    await sam.evaluate(async () => {
        const form = document.querySelector('.ux-dashboard-toolbar');
        const data = new FormData();
        data.append('_token', form.querySelector('[name=_token]').value);
        data.append('dashboard', 'main');
        data.append('action', 'save');
        form.querySelectorAll('[name="known[]"]').forEach((input) => data.append('known[]', input.value));
        data.append('widgets[]', 'inventory.value');
        data.append('widgets[]', 'products.count');
        await fetch(form.getAttribute('action'), { method: 'POST', body: data });
    });
    await sam.reload();
    const known = await sam.evaluate(() => [...document.querySelectorAll('[name="known[]"]')].map((input) => input.value));
    expect(known).not.toContain('inventory.value');
    expect(await widgets(sam), 'forbidden widget dropped').toEqual(['products.count:small']);

    const status = await sam.evaluate(async () => {
        const data = new FormData();
        data.append('_token', 'x');
        data.append('dashboard', 'main');
        data.append('action', 'reset');
        return (await fetch(document.querySelector('.ux-dashboard-toolbar').getAttribute('action'), { method: 'POST', body: data })).status;
    });
    expect(status, 'bad token').toBe(403);
});

test('reset restores the defaults', async () => {
    await sam.click('[data-ux-dashboard-customize]');
    await confirmAndReload(sam, '[data-ux-dashboard-reset]');
    expect(await widgets(sam)).toHaveLength(4);
});

test('Arabic and right-to-left', async () => {
    await sam.goto('/admin?_locale=ar');
    expect(await sam.evaluate(() => document.documentElement.dir)).toBe('rtl');
    expect(await noHorizontalOverflow(sam)).toBe(true);
    await expect(sam.locator('[data-ux-dashboard-customize]')).toHaveText('تخصيص');
    await sam.goto('/admin?_locale=en');
});

test('no JavaScript errors', () => {
    expect(ana.errors).toEqual([]);
    expect(sam.errors).toEqual([]);
});
