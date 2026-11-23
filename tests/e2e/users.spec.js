import { test, expect } from './support/test.js';
import { accounts, storageState } from './support/env.js';
import { apiRequest, confirmDeletion, expectToast, randomPassword, rowWith, uniqueSuffix } from './support/helpers.js';

test.describe('users as an administrator', () => {
    test.use({ storageState: storageState.admin2 });

    test('lists accounts, newest first', async ({ page }) => {
        const email = `e2e-listed-${uniqueSuffix()}@example.test`;
        await page.goto('/users');
        await expect(page.getByRole('heading', { name: 'Users', exact: true })).toBeVisible();

        const created = await apiRequest(page, 'POST', '/api/user', {
            name: 'E2E Listed User',
            email,
            password: randomPassword(),
            type: 'user',
        });
        expect(created.status).toBe(200);

        await page.reload();
        const firstRow = page.locator('tbody tr').first();
        await expect(firstRow).toContainText(email);
        await expect(firstRow).toContainText('E2E Listed User');
        await expect(firstRow).toContainText('Yes');
    });

    test('cannot delete their own account', async ({ page }) => {
        await page.goto('/developer');
        const profile = await apiRequest(page, 'GET', '/api/profile');
        expect(profile.body?.data?.email).toBe(accounts.admin2.email);

        const response = await apiRequest(page, 'DELETE', `/api/user/${profile.body.data.id}`);
        expect(response.status).toBe(403);
    });

    test('creates, edits and deletes a user', async ({ page }) => {
        const suffix = uniqueSuffix();
        const name = `E2E User ${suffix}`;
        const renamed = `E2E Renamed ${suffix}`;
        const email = `e2e-user-${suffix}@example.test`;

        await page.goto('/users');

        await test.step('create', async () => {
            await page.getByRole('button', { name: 'Add new' }).click();
            const dialog = page.getByRole('dialog', { name: 'Create new user' });
            await dialog.getByLabel('Name').fill(name);
            await dialog.getByLabel('Email').fill(email);
            await dialog.getByLabel('Password').fill(randomPassword());
            await dialog.getByLabel('Type').selectOption('user');
            await dialog.getByRole('button', { name: 'Create' }).click();

            await expectToast(page, 'User Created Successfully');
            await expect(dialog).toBeHidden();
            const row = rowWith(page, email);
            await expect(row).toContainText(name);
            // Accounts created by an administrator are verified automatically.
            await expect(row).toContainText('Yes');
        });

        await test.step('edit', async () => {
            await page.getByRole('button', { name: `Edit ${name}` }).click();
            const dialog = page.getByRole('dialog', { name: 'Update user' });
            await expect(dialog.getByLabel('Email')).toHaveValue(email);
            await dialog.getByLabel('Name').fill(renamed);
            await dialog.getByRole('button', { name: 'Update' }).click();

            await expectToast(page, 'User Information has been updated');
            await expect(rowWith(page, email)).toContainText(renamed);
        });

        await test.step('delete', async () => {
            await page.getByRole('button', { name: `Delete ${renamed}` }).click();
            await confirmDeletion(page);

            await expectToast(page, 'User has been Deleted');
            await expect(rowWith(page, email)).toHaveCount(0);
        });
    });

    test('shows validation errors from the server', async ({ page }) => {
        await page.goto('/users');
        await page.getByRole('button', { name: 'Add new' }).click();
        const dialog = page.getByRole('dialog', { name: 'Create new user' });
        await dialog.getByLabel('Name').fill(`E2E Invalid ${uniqueSuffix()}`);
        await dialog.getByLabel('Email').fill(accounts.admin.email);
        await dialog.getByLabel('Password').fill('short');
        await dialog.getByLabel('Type').selectOption('user');
        await dialog.getByRole('button', { name: 'Create' }).click();

        await expect(dialog.getByText('The email has already been taken.')).toBeVisible();
        await expect(dialog.getByText(/password field must be at least 12 characters/i)).toBeVisible();
        await expect(dialog).toBeVisible();
    });
});

test.describe('users as a regular user', () => {
    test.use({ storageState: storageState.user });

    test('has no access to account management', async ({ page }) => {
        await page.goto('/dashboard');
        const menu = page.getByRole('navigation', { name: 'Main navigation' });
        await expect(menu.getByRole('link', { name: 'Products' })).toBeVisible();
        await expect(menu.getByRole('link', { name: 'Users' })).toHaveCount(0);
        await expect(menu.getByRole('link', { name: 'Categories' })).toHaveCount(0);
        await expect(menu.getByRole('link', { name: 'Tags' })).toHaveCount(0);

        await page.goto('/users');
        await expect(page.getByRole('heading', { name: 'Page not found', exact: true })).toBeVisible();
        await expect(page.getByText('Oops! Page not found.')).toBeVisible();
        await expect(page.getByRole('table')).toHaveCount(0);

        for (const [method, url, body] of [
            ['GET', '/api/user'],
            ['POST', '/api/user', { name: 'x', email: 'x@example.test', password: randomPassword(), type: 'admin' }],
            ['DELETE', '/api/user/1'],
        ]) {
            const response = await apiRequest(page, method, url, body);
            expect(response.status, `${method} ${url}`).toBe(403);
        }
    });
});
