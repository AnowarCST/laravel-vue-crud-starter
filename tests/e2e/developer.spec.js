import { test, expect } from './support/test.js';
import { accounts, storageState } from './support/env.js';
import { confirmDeletion, expectToast, rowWith, uniqueSuffix } from './support/helpers.js';

test.use({ storageState: storageState.user });

test('a user can create a personal access token, use it, and revoke it', async ({ page, request }) => {
    const name = `E2E token ${uniqueSuffix()}`;
    let token;

    await page.goto('/developer');
    await expect(page.getByRole('heading', { name: 'Personal access tokens' })).toBeVisible();

    await test.step('create', async () => {
        await page.getByRole('button', { name: 'Create new token' }).click();
        const dialog = page.getByRole('dialog', { name: 'Create token' });
        await dialog.getByLabel('Name').fill(name);
        await dialog.getByRole('button', { name: 'Create' }).click();

        const tokenDialog = page.getByRole('dialog', { name: 'Personal access token' });
        await expect(tokenDialog.getByText('Copy this token now.')).toBeVisible();
        token = await tokenDialog.getByLabel('Personal access token').inputValue();
        expect(token.length).toBeGreaterThan(100);

        await tokenDialog.getByRole('button', { name: 'Done' }).click();
        await expect(tokenDialog).toBeHidden();
        await expect(rowWith(page, name)).toBeVisible();
    });

    await test.step('the token authenticates API requests', async () => {
        const response = await request.get('/api/profile', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        expect(response.status()).toBe(200);
        expect((await response.json()).data.email).toBe(accounts.user.email);
    });

    await test.step('revoke', async () => {
        await rowWith(page, name).getByRole('button', { name: 'Revoke' }).click();
        await confirmDeletion(page);
        await expectToast(page, 'Token revoked');
        await expect(rowWith(page, name)).toHaveCount(0);

        const response = await request.get('/api/profile', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        expect(response.status()).toBe(401);
    });
});
