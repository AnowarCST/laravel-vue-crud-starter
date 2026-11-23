import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';

// Every test in this suite already fails on a CSP violation or an uncaught
// page error (see support/test.js). These tests visit every page on purpose,
// so a page that no other test opens is still checked.

const guestPages = ['/', '/login', '/register', '/password/reset'];
const appPages = [
    '/dashboard',
    '/users',
    '/products',
    '/product/category',
    '/product/tag',
    '/profile',
    '/developer',
    '/this-page-does-not-exist',
];

function expectSecurityHeaders(response) {
    const headers = response.headers();
    expect(headers['content-security-policy']).toMatch(/script-src 'self' 'nonce-[^']+'/);
    expect(headers['content-security-policy']).toContain("object-src 'none'");
    expect(headers['x-frame-options']).toBe('SAMEORIGIN');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['permissions-policy']).toBe('camera=(), microphone=(), geolocation=()');
}

test('guest pages load without CSP violations and send security headers', async ({ page }) => {
    for (const path of guestPages) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBe(200);
        expectSecurityHeaders(response);
        await page.waitForLoadState('networkidle');
    }
});

test.describe('signed in as an administrator', () => {
    test.use({ storageState: storageState.admin2 });

    test('every dashboard page loads without CSP violations', async ({ page }) => {
        for (const path of appPages) {
            const response = await page.goto(path);
            expect(response?.status(), path).toBe(200);
            expectSecurityHeaders(response);
            await expect(page.locator('.app-content')).toBeVisible();
            await page.waitForLoadState('networkidle');
        }
    });

    // .zap/rules.tsv ignores ZAP's HttpOnly warning, which only concerns XSRF-TOKEN.
    test('session cookies are HttpOnly and SameSite=Lax; only XSRF-TOKEN is readable by scripts', async ({ page }) => {
        await page.goto('/dashboard');
        const cookies = Object.fromEntries((await page.context().cookies()).map((cookie) => [cookie.name, cookie]));

        for (const name of ['laravel_starter_session', 'laravel_token']) {
            expect(cookies[name], name).toBeDefined();
            expect(cookies[name].httpOnly, `${name} is HttpOnly`).toBe(true);
            expect(cookies[name].sameSite, `${name} SameSite`).toBe('Lax');
        }
        expect(cookies['XSRF-TOKEN'].httpOnly).toBe(false);
        expect(await page.evaluate(() => document.cookie)).not.toMatch(/laravel_starter_session|laravel_token/);
    });
});

test('the CSP check used by every test detects a blocked inline script', async ({ page, browserIssues }) => {
    await page.goto('/login');
    await page.evaluate(() => {
        const script = document.createElement('script');
        script.textContent = 'window.__e2eInlineScriptRan = true;';
        document.body.append(script);
    });

    await expect.poll(() => browserIssues.issues.map((issue) => issue.kind)).toContain('csp-event');
    expect(await page.evaluate(() => window.__e2eInlineScriptRan)).toBeUndefined();

    // This violation was caused on purpose; clear it so the test passes.
    browserIssues.issues.length = 0;
});
