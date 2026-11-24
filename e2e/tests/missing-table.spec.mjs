import { test, expect } from '@playwright/test';
import { resetDemo, signIn, sql } from './support.mjs';
import { MOVED, TABLE, restorePreferenceTable } from './preference-table.mjs';

// Covers apps that installed the bundle but have not created the preference table yet.
test.describe.configure({ mode: 'serial' });

let page;

test.beforeAll(async ({ browser }) => {
    restorePreferenceTable();
    resetDemo();
    page = await signIn(browser, 'ana');
    sql(`rename table ${TABLE} to ${MOVED}`);
});

test.afterAll(() => {
    try {
        restorePreferenceTable();
    } finally {
        resetDemo();
    }
});

test('pages still render with default preferences', async () => {
    for (const url of ['/admin', '/admin/product']) {
        const response = await page.goto(url);
        expect(response.status(), url).toBe(200);
        expect(await page.evaluate(() => document.body.dataset.uxLayout), url).toBe('sidebar');
        if ('/admin' === url) await expect(page.locator('.ux-widget'), 'default widgets').toHaveCount(5);
    }
});

test('saving shows a warning instead of an error', async () => {
    await page.goto('/admin/product');
    await page.click('.content-top .ux-layout-button');
    await page.waitForSelector('.content-top .ux-layout-option[value="compact"]', { state: 'visible' });
    const [response] = await Promise.all([page.waitForNavigation(), page.click('.content-top .ux-layout-option[value="compact"]')]);
    expect(response.status()).toBe(200);
    expect(page.url()).toContain('/admin/product');
    await expect(page.locator('body')).toContainText('could not be saved');
});

test('saving the dashboard shows a warning instead of an error', async () => {
    await page.goto('/admin');
    await page.click('[data-ux-dashboard-customize]');
    await page.click('[data-widget="products.count"] [data-ux-widget-remove]');
    const [response] = await Promise.all([page.waitForNavigation(), page.click('.ux-dashboard-editbar button[value=save]')]);
    expect(response.status()).toBe(200);
    await expect(page.locator('body')).toContainText('could not be saved');
    await expect(page.locator('.ux-widget'), 'layout unchanged').toHaveCount(5);
});

test('no JavaScript errors', () => {
    expect(page.errors).toEqual([]);
});
