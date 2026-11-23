import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';

// The E2E stack runs with APP_ENV=production and API_DOCS_ENABLED=true (see
// tests/e2e/compose.yaml), so /docs follows the production rules: only
// signed-in administrators with a verified email address may open it. In the
// local environment (APP_ENV=local) it is open to everyone instead; that
// case is covered by tests/Feature/Docs/ApiDocsTest.php.

test.describe('as an administrator', () => {
    test.use({ storageState: storageState.admin });

    test('/docs shows the API documentation without CSP violations', async ({ page }) => {
        const response = await page.goto('/docs');
        expect(response?.status()).toBe(200);
        await expect(page).toHaveTitle(/^API documentation · /);

        // Swagger UI renders the document fetched from /docs/openapi.yaml.
        await expect(page.getByRole('heading', { name: /Laravel Vue CRUD Starter API/ })).toBeVisible();
        const stats = page.locator('#operations-Products-getProductStats');
        await expect(stats).toBeVisible();
        await expect(stats).toContainText('/product/stats');

        // Opening an operation shows its details, rendered from the document.
        await stats.locator('.opblock-summary-control').click();
        await expect(stats.getByText('Product counts for the dashboard.')).toBeVisible();
    });

    test('/docs/openapi.yaml returns the OpenAPI document', async ({ page }) => {
        // page.request sends the signed-in session's cookies.
        const response = await page.request.get('/docs/openapi.yaml');
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toMatch(/^application\/yaml\b/);
        expect(await response.text()).toMatch(/^openapi: 3\.1\.\d+$/m);
    });
});

test.describe('as a regular user', () => {
    test.use({ storageState: storageState.user });

    test('/docs is forbidden', async ({ page }) => {
        const response = await page.goto('/docs');
        expect(response?.status()).toBe(403);
    });
});

test('guests are sent to the login page', async ({ page }) => {
    await page.goto('/docs');
    await expect(page).toHaveURL(/\/login$/);
});
