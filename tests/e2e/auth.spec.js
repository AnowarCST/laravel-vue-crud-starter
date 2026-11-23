import { test, expect } from './support/test.js';
import { accounts } from './support/env.js';
import { login } from './support/helpers.js';

test.describe('authentication', () => {
    test('guests are redirected to the login page', async ({ page }) => {
        for (const path of ['/dashboard', '/users', '/products', '/profile']) {
            await page.goto(path);
            await expect(page).toHaveURL(/\/login$/);
        }
        await expect(page.getByRole('button', { name: 'Sign In' })).toBeVisible();
    });

    test('a wrong password is rejected', async ({ page }) => {
        await login(page, { email: accounts.user.email, password: 'not-the-right-password' });

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByText('These credentials do not match our records.')).toBeVisible();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });

    // The administrators sign in through the same form in auth.setup.js.
    test('a user can sign in, sees the dashboard, and can sign out', async ({ page }) => {
        const { user } = accounts;
        await login(page, user);

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
        await expect(page.locator('.app-header')).toContainText(user.name);

        await page.getByRole('navigation', { name: 'Main navigation' }).getByRole('button', { name: 'Logout' }).click();
        await expect(page).not.toHaveURL(/\/dashboard$/);

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/login$/);
    });
});
