import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';

test.use({ storageState: storageState.user });

test('an unknown page shows the not found page with a way back', async ({ page }) => {
    await page.goto('/this-page-does-not-exist');

    await expect(page.getByRole('heading', { name: 'Page not found', exact: true })).toBeVisible();
    await expect(page.getByText('Oops! Page not found.')).toBeVisible();
    await expect(page).toHaveTitle(/^Page not found · /);

    await page.getByRole('link', { name: 'Back to dashboard' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
});

test('an unknown API endpoint returns a JSON 404', async ({ request }) => {
    const response = await request.get('/api/this-endpoint-does-not-exist', {
        headers: { Accept: 'application/json' },
    });
    expect(response.status()).toBe(404);
    expect(await response.json()).toEqual({ message: 'Not Found.' });
});
