import { test as setup, expect } from '@playwright/test';
import { accounts, storageState } from './support/env.js';
import { login } from './support/helpers.js';

// Sign in once per role and save the session cookies, so most tests do not
// have to go through the login form (which is rate limited per IP).
for (const role of ['admin', 'admin2', 'user']) {
    setup(`sign in as ${role}`, async ({ page }) => {
        const account = accounts[role];
        await login(page, account);
        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Dashboard', level: 3 })).toBeVisible();
        await page.context().storageState({ path: storageState[role] });
    });
}
