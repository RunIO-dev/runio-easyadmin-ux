import { test, expect } from '@playwright/test';
import { PASSWORD, noHorizontalOverflow, productValue, resetDemo, signIn, sql } from './support.mjs';

test.describe.configure({ mode: 'serial' });

// Seeded product 1: "Wireless Headphones", MG-0001, 3349 cents, stock 7. Product 2 has stock 14.
let page;
let drawer;

const list = async (query = 'sort[id]=ASC') => {
    await page.goto(`/admin/product?${query}`);
    await page.waitForSelector('.ux-cell-edit', { state: 'attached' });
};
const cell = (column, id = 1) => page.locator(`tr[data-id="${id}"] td[data-column="${column}"]`);
const openEditor = async (column, id = 1) => {
    await cell(column, id).hover();
    await cell(column, id).locator('.ux-cell-edit').click();
};
const editorClosed = () => page.waitForFunction(() => !document.querySelector('.ux-cell-editor'));
const editorError = () => page.waitForFunction(() => document.querySelector('.ux-cell-error')?.textContent.length > 0);
const signInAgain = async () => {
    await page.goto('/login');
    await page.fill('#username', 'ana');
    await page.fill('#password', PASSWORD);
    await Promise.all([page.waitForURL('**/admin'), page.click('button[type=submit]')]);
};
/** Closes the drawer with its header button and answers the unsaved-changes prompt. */
const closeDrawerAnswering = async (accept) => {
    const dialog = page.waitForEvent('dialog');
    const click = page.click('.ux-drawer header button');
    const prompt = await dialog;
    const message = prompt.message();
    await (accept ? prompt.accept() : prompt.dismiss());
    await click;
    return message;
};

test.beforeAll(async ({ browser }) => {
    resetDemo();
    page = await signIn(browser, 'ana');
    drawer = page.frameLocator('dialog.ux-drawer iframe');
});

test.afterAll(() => resetDemo());

test('text cells edit in place', async () => {
    await list();
    await expect(cell('active').locator('.ux-cell-edit'), 'switches have no pencil').toHaveCount(0);

    await openEditor('name');
    await page.waitForSelector('.ux-cell-editor input');
    expect(page.url(), 'stays on the list').toContain('/admin/product?');
    await page.fill('.ux-cell-editor input', 'Edited Name');
    await page.keyboard.press('Escape');
    await expect(page.locator('.ux-cell-editor'), 'Escape closes').toHaveCount(0);
    expect(productValue('name'), 'Escape does not save').toBe('Wireless Headphones');

    await openEditor('name');
    await page.fill('.ux-cell-editor input', 'Edited Name');
    await page.keyboard.press('Enter');
    await editorClosed();
    expect(productValue('name')).toBe('Edited Name');
    await expect(cell('name')).toContainText('Edited Name');
    expect([productValue('sku'), productValue('stock'), productValue('price_in_cents')], 'other fields kept').toEqual(['MG-0001', '7', '3349']);
    await expect(cell('name').locator('.ux-cell-edit'), 'pencil comes back').toHaveCount(1);
});

test('validation errors keep the editor open', async () => {
    await openEditor('stock');
    await page.fill('.ux-cell-editor input', '-4');
    await page.keyboard.press('Enter');
    await editorError();
    expect((await page.innerText('.ux-cell-error')).length).toBeGreaterThan(3);
    expect(productValue('stock')).toBe('7');

    await page.fill('.ux-cell-editor input', '9');
    await page.keyboard.press('Enter');
    await editorClosed();
    expect(productValue('stock'), 'recovers after an error').toBe('9');

    await openEditor('sku');
    await page.fill('.ux-cell-editor input', 'MG-0002');
    await page.keyboard.press('Enter');
    await editorError();
    expect(productValue('sku'), 'unique constraint').toBe('MG-0001');
    await page.keyboard.press('Escape');
});

test('money and association fields', async () => {
    await openEditor('priceInCents');
    await page.waitForSelector('.ux-cell-editor input');
    expect(await page.inputValue('.ux-cell-editor input'), 'money shown as decimal').toBe('33.49');
    await page.fill('.ux-cell-editor input', '40.25');
    await page.keyboard.press('Enter');
    await editorClosed();
    expect(productValue('price_in_cents')).toBe('4025');
    await expect(cell('priceInCents')).toContainText('40.25');

    const before = productValue('category_id');
    await openEditor('category');
    await page.waitForSelector('.ux-cell-editor select');
    const other = await page.$$eval('.ux-cell-editor select option', (options, current) => options.map((o) => o.value).find((v) => v && v !== current), before);
    await page.selectOption('.ux-cell-editor select', other);
    await page.click('.ux-cell-editor .btn-primary');
    await editorClosed();
    expect(productValue('category_id')).toBe(other);
    sql(`update product set category_id = ${Number(before)} where id = 1`);
});

test('a change made by someone else is not silently overwritten', async () => {
    await list();
    await openEditor('stock');
    await page.waitForSelector('.ux-cell-editor input');
    sql('update product set stock = 77 where id = 1');
    await page.fill('.ux-cell-editor input', '5');
    await page.keyboard.press('Enter');
    await editorError();
    expect(productValue('stock'), 'conflict blocks the save').toBe('77');
    await page.keyboard.press('Enter');
    await editorClosed();
    expect(productValue('stock'), 'second Enter overrides').toBe('5');
});

test('row click opens the drawer, which validates and saves', async () => {
    await list();
    await cell('sku').click({ position: { x: 10, y: 10 } });
    await page.waitForSelector('dialog.ux-drawer[open]');
    expect(page.url()).toContain('/admin/product?');
    await drawer.locator('#Product_name').waitFor();
    await expect(drawer.locator('.sidebar-wrapper'), 'drawer hides the sidebar').toBeHidden();

    await drawer.locator('#Product_stock').fill('-1');
    await drawer.locator('.action-saveAndReturn').click();
    await drawer.locator('.invalid-feedback, .form-error-message').first().waitFor();
    await expect(page.locator('dialog.ux-drawer[open]')).toHaveCount(1);
    expect(productValue('stock')).toBe('5');

    await drawer.locator('#Product_stock').fill('6');
    expect((await closeDrawerAnswering(false)).length, 'unsaved changes prompt').toBeGreaterThan(0);
    await expect(page.locator('dialog.ux-drawer[open]'), 'dismissing keeps the drawer').toHaveCount(1);

    await Promise.all([page.waitForNavigation(), drawer.locator('.action-saveAndReturn').click()]);
    await page.waitForSelector('.ux-cell-edit', { state: 'attached' });
    expect(productValue('stock')).toBe('6');
    await expect(cell('stock'), 'list refreshed').toContainText('6');
    await expect(page.locator('dialog.ux-drawer[open]')).toHaveCount(0);
});

test('the edit action opens the drawer and Escape closes it', async () => {
    await page.locator('tr[data-id="2"] .dropdown-toggle').click();
    await page.locator('tr[data-id="2"] a[data-action-name=edit]').click();
    await page.waitForSelector('dialog.ux-drawer[open]');
    await drawer.locator('#Product_name').waitFor();
    await drawer.locator('#Product_name').focus();
    await page.keyboard.press('Escape');
    await page.waitForSelector('dialog.ux-drawer:not([open])', { state: 'attached' });
});

test('a failed save still counts as unsaved changes', async () => {
    await list();
    await cell('sku', 2).click({ position: { x: 10, y: 10 } });
    await page.waitForSelector('dialog.ux-drawer[open]');
    await drawer.locator('#Product_stock').fill('-1');
    await drawer.locator('.action-saveAndReturn').click();
    await drawer.locator('.invalid-feedback, .form-error-message').first().waitFor();
    expect((await closeDrawerAnswering(true)).length).toBeGreaterThan(0);
    await page.waitForSelector('dialog.ux-drawer:not([open])', { state: 'attached' });
    expect(productValue('stock', 2)).toBe('14');
});

test('row clicks follow EasyAdmin rules', async () => {
    await list();
    await page.locator('tr[data-id="3"] .form-batch-checkbox').check();
    await cell('sku', 3).click({ position: { x: 10, y: 10 } });
    await page.waitForTimeout(250);
    await expect(page.locator('dialog.ux-drawer[open]'), 'selected row does not open').toHaveCount(0);
    await page.locator('tr[data-id="3"] .form-batch-checkbox').uncheck();

    await page.locator('tr[data-id="3"] td.actions').click({ position: { x: 2, y: 2 } });
    await page.waitForTimeout(150);
    await expect(page.locator('dialog.ux-drawer[open]'), 'actions cell does not open').toHaveCount(0);

    // Positive control: the same click on an unselected cell opens the drawer within that time.
    await cell('sku', 3).click({ position: { x: 10, y: 10 } });
    await page.waitForTimeout(150);
    await expect(page.locator('dialog.ux-drawer[open]'), 'control: plain row click opens').toHaveCount(1);
    await page.keyboard.press('Escape');
    await page.waitForSelector('dialog.ux-drawer:not([open])', { state: 'attached' });

    await list();
    await page.evaluate(() => {
        document.querySelector('tr[data-id="1"] td[data-column=name] .ux-cell-edit').click();
        document.querySelector('tr[data-id="1"] td[data-column=sku] .ux-cell-edit').click();
    });
    await page.waitForSelector('.ux-cell-editor');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.ux-cell-editor'), 'rapid clicks leave one editor').toHaveCount(1);
    await page.keyboard.press('Escape');
});

test('an expired session never saves', async () => {
    await list();
    await openEditor('name');
    await page.waitForSelector('.ux-cell-editor input');
    await page.context().clearCookies();
    const posts = [];
    const recordPost = (request) => { if ('POST' === request.method()) posts.push(request.url()); };
    page.on('request', recordPost);
    await page.fill('.ux-cell-editor input', 'Hacked');
    await page.keyboard.press('Enter');
    // Without a session the edit form cannot be loaded, so the editor reports a failure and never posts.
    await editorError();
    page.off('request', recordPost);
    expect(posts, 'no save request sent').toEqual([]);
    expect(productValue('name')).toBe('Edited Name');
    await page.keyboard.press('Escape');
    await signInAgain();
});

test('right-to-left layouts, editor and drawer', async () => {
    await list('_locale=ar&sort[id]=ASC');
    await page.waitForSelector('html[dir=rtl]');
    for (const layout of ['sidebar', 'compact', 'topbar', 'focus']) {
        await page.locator('.ux-layout-button').click();
        await Promise.all([page.waitForNavigation(), page.locator(`.ux-layout-option[value=${layout}]`).click()]);
        await page.waitForSelector('html[dir=rtl]');
        expect(await noHorizontalOverflow(page), `rtl ${layout}: no overflow`).toBe(true);
    }
    await page.locator('.ux-layout-button').click();
    await Promise.all([page.waitForNavigation(), page.locator('.ux-layout-option[value=sidebar]').click()]);

    const toolbar = await page.locator('.ux-table-toolbar').boundingBox();
    expect(toolbar.x).toBeGreaterThanOrEqual(0);
    expect(toolbar.x + toolbar.width).toBeLessThanOrEqual(1440);

    await openEditor('sku');
    await page.waitForSelector('.ux-cell-editor input');
    const editor = await page.locator('.ux-cell-editor').boundingBox();
    const box = await cell('sku').boundingBox();
    expect(Math.abs(editor.x + editor.width - (box.x + box.width)), 'editor anchored to the right edge').toBeLessThan(8);
    expect(editor.x).toBeGreaterThanOrEqual(0);
    await page.keyboard.press('Escape');

    await cell('sku').click({ position: { x: box.width - 12, y: 10 } });
    await page.waitForSelector('dialog.ux-drawer[open]');
    await drawer.locator('#Product_name').waitFor();
    expect((await page.locator('dialog.ux-drawer').boundingBox()).x, 'drawer on the left').toBeLessThanOrEqual(1);
    await expect(page.locator('.ux-drawer header')).toContainText('تعديل');
    await page.keyboard.press('Escape');
    await page.goto('/admin/product?_locale=en');
    await page.waitForSelector('html[dir=ltr]');
});

test('phones get no pencil', async () => {
    await page.setViewportSize({ width: 390, height: 800 });
    await list();
    await expect(page.locator('.ux-cell-edit').first()).toBeHidden();
    await page.setViewportSize({ width: 1440, height: 900 });
});

test('no JavaScript errors', () => {
    expect(page.errors).toEqual([]);
});
