import { test, expect } from '@playwright/test';
import { BASE_URL, PHONE, noHorizontalOverflow, resetDemo, signIn } from './support.mjs';

test.describe.configure({ mode: 'serial' });

const LAYOUTS = ['sidebar', 'compact', 'topbar', 'focus'];
let ana;

const layoutOf = (page) => page.evaluate(() => document.body.dataset.uxLayout);

const switchTo = async (page, id) => {
    await page.click('.content-top .ux-layout-button');
    await page.waitForSelector(`.content-top .ux-layout-option[value="${id}"]`, { state: 'visible' });
    await Promise.all([page.waitForNavigation(), page.click(`.content-top .ux-layout-option[value="${id}"]`)]);
};

test.beforeAll(async ({ browser }) => {
    resetDemo();
    ana = await signIn(browser, 'ana');
});

test.afterAll(() => resetDemo());

test('every layout renders the list without overflow', async () => {
    await ana.goto('/admin/product');
    expect(await layoutOf(ana), 'default layout').toBe('sidebar');

    for (const id of LAYOUTS) {
        if ((await layoutOf(ana)) !== id) await switchTo(ana, id);
        expect(await layoutOf(ana)).toBe(id);
        expect(ana.url(), `${id}: stays on product list`).toMatch(/\/admin\/product$/);
        expect(await noHorizontalOverflow(ana), `${id}: no horizontal overflow`).toBe(true);
        await expect(ana.locator('table.datagrid')).toBeVisible();
    }
});

test('top bar layout places navigation above content with hover submenus', async () => {
    await switchTo(ana, 'topbar');
    const nav = await ana.locator('.ea-sidebar').first().boundingBox();
    const main = await ana.locator('.main-content').first().boundingBox();
    expect(nav.y + nav.height, 'nav sits above content').toBeLessThanOrEqual(main.y + 1);
    expect(nav.width, 'nav spans the page').toBeGreaterThan(1300);

    await ana.reload();
    expect(await layoutOf(ana), 'choice survives reload').toBe('topbar');

    const item = ana.locator('.ea-sidebar-item.has-submenu').first();
    const submenu = item.locator('> .ea-sidebar-submenu');
    await expect(submenu, 'submenu closed on load').toBeHidden();
    await item.hover();
    await expect(submenu, 'submenu opens on hover').toBeVisible();
    const flyout = await submenu.boundingBox();
    const trigger = await item.boundingBox();
    expect(flyout.y, 'submenu drops below its item').toBeGreaterThanOrEqual(trigger.y + trigger.height - 1);

    await Promise.all([ana.waitForNavigation(), submenu.locator('.ea-sidebar-submenu-items a', { hasText: 'All categories' }).click()]);
    expect(ana.url()).toContain('/admin/category');
    await ana.mouse.move(700, 600);
    await expect(ana.locator('.ea-sidebar-item.has-submenu > .ea-sidebar-submenu').first(), 'active submenu stays closed').toBeHidden();
});

test('layout choice is per user', async ({ browser }) => {
    const sam = await signIn(browser, 'sam');
    expect(await layoutOf(sam)).toBe('sidebar');
    expect(sam.errors).toEqual([]);
    await sam.context().close();
});

test('command palette opens, filters and navigates', async () => {
    await ana.goto('/admin/product');
    await ana.keyboard.press('Control+k');
    await expect(ana.locator('dialog.ux-palette')).toBeVisible();
    await ana.keyboard.type('categ');
    await expect(ana.locator('.ux-palette li[role=option]')).toHaveCount(1);
    await Promise.all([ana.waitForNavigation(), ana.keyboard.press('Enter')]);
    expect(ana.url()).toContain('/admin/category');

    await ana.goto('/admin/product');
    await ana.focus('input[name=query]');
    await ana.keyboard.press('Control+k');
    await expect(ana.locator('dialog.ux-palette'), 'Ctrl+K left alone in text fields').toBeHidden();
    await ana.evaluate(() => document.activeElement.blur());
    await ana.click('.content-top [data-ux-palette-open]');
    await expect(ana.locator('dialog.ux-palette')).toBeVisible();
    await ana.keyboard.type('zzzz');
    await expect(ana.locator('.ux-palette [role=status]')).toHaveText('No results');
    expect(await ana.getAttribute('.ux-palette input', 'aria-label')).toBeTruthy();
    await ana.keyboard.press('Escape');
});

test('EasyAdmin row action menu still works', async () => {
    await ana.goto('/admin/product');
    await ana.locator('table.datagrid tbody tr').first().locator('.dropdown-toggle, [data-bs-toggle=dropdown]').first().click();
    await expect(ana.locator('table.datagrid tbody tr .dropdown-menu.show')).toBeVisible();
    await ana.keyboard.press('Escape');
});

test('layout endpoint rejects bad requests', async ({ browser }) => {
    const post = (body) =>
        ana.evaluate(async ({ url, body }) => (await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(body),
            redirect: 'manual',
        })).status, { url: `${BASE_URL}/admin/_ux/layout`, body });

    expect(await post({ layout: 'focus', _token: 'wrong' }), 'bad CSRF').toBe(403);
    const token = await ana.getAttribute('.content-top input[name=_token]', 'value');
    expect(await post({ layout: 'nope', _token: token }), 'unknown layout').toBe(400);
    await ana.reload();
    expect(await layoutOf(ana), 'rejected requests changed nothing').toBe('topbar');

    const anonymous = await browser.newContext();
    const response = await anonymous.request.post(`${BASE_URL}/admin/_ux/layout`, { form: { layout: 'focus' }, maxRedirects: 0 });
    expect(response.status(), 'expired session redirects').toBe(302);
    expect(response.headers().location).toMatch(/\/login$/);
    await anonymous.close();
});

test('prefetch rules skip unsafe links', async () => {
    const rules = await ana.evaluate(() => JSON.parse(document.querySelector('script[type=speculationrules]').textContent));
    const prefetchable = (url) => ana.evaluate(({ rules, url }) => {
        // Selector clauses need a real link element, so only URL clauses are evaluated here.
        const matches = (clause) => {
            if (clause.and) return clause.and.filter((part) => !part.selector_matches && !part.not?.selector_matches).every(matches);
            if (clause.or) return clause.or.some(matches);
            if (clause.not) return !matches(clause.not);
            if (clause.href_matches) return new URLPattern(clause.href_matches, location.href).test(new URL(url, location.href));
            return true;
        };
        return matches(rules.prefetch[0].where);
    }, { rules, url });

    expect(await prefetchable('/admin/category')).toBe(true);
    expect(await prefetchable('/admin/product?page=2')).toBe(true);
    for (const url of ['/logout', '/admin/logout', '/admin?_switch_user=bob', '/admin/product?_switch_user=_exit', '/reports/run']) {
        expect(await prefetchable(url), `blocks ${url}`).toBe(false);
    }

    await ana.goto('/admin/product');
    await ana.locator('.ea-sidebar a', { hasText: 'Log out' }).first().hover();
    await ana.waitForTimeout(700);
    await ana.mouse.move(700, 600);
    await ana.reload();
    expect(ana.url(), 'hovering logout keeps the session').toContain('/admin/product');
});

test('switching layout returns to the exact URL', async () => {
    await ana.goto('/admin/product?page=1');
    await switchTo(ana, 'compact');
    expect(ana.url()).toMatch(/\/admin\/product\?page=1$/);
    expect(await ana.evaluate(() =>
        [...document.styleSheets].some((sheet) => {
            try { return [...sheet.cssRules].some((rule) => rule.cssText.includes('view-transition')); } catch { return false; }
        })
    ), 'view transitions declared').toBe(true);
});

test('phone width has no horizontal overflow', async ({ browser }) => {
    const phone = await signIn(browser, 'sam', { viewport: PHONE });
    await phone.goto('/admin/product');
    expect(await noHorizontalOverflow(phone)).toBe(true);
    expect(phone.errors).toEqual([]);
    await phone.context().close();
});

test('no JavaScript errors', () => {
    expect(ana.errors).toEqual([]);
});
