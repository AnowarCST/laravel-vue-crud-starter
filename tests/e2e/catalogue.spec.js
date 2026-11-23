import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';
import { confirmDeletion, expectToast, rowWith, uniqueSuffix } from './support/helpers.js';

test.use({ storageState: storageState.admin });

test('an administrator can create, edit and delete a category', async ({ page }) => {
    const suffix = uniqueSuffix();
    const name = `E2E Category ${suffix}`;
    const renamed = `E2E Category Renamed ${suffix}`;

    await page.goto('/product/category');
    await expect(page.getByRole('heading', { name: 'Categories', exact: true })).toBeVisible();

    await test.step('create', async () => {
        await page.getByRole('button', { name: 'Add new' }).click();
        const dialog = page.getByRole('dialog', { name: 'Create new category' });
        await dialog.getByLabel('Name').fill(name);
        await dialog.getByLabel('Description').fill('Created by the end-to-end tests.');
        await dialog.getByRole('button', { name: 'Create' }).click();

        await expectToast(page, 'Category Created Successfully');
        await expect(rowWith(page, name)).toContainText('Created by the end-to-end tests.');
    });

    await test.step('edit', async () => {
        await page.getByRole('button', { name: `Edit ${name}` }).click();
        const dialog = page.getByRole('dialog', { name: 'Update category' });
        await expect(dialog.getByLabel('Name')).toHaveValue(name);
        await dialog.getByLabel('Name').fill(renamed);
        await dialog.getByRole('button', { name: 'Update' }).click();

        await expectToast(page, 'Category Information has been updated');
        await expect(rowWith(page, renamed)).toBeVisible();
        await expect(page.getByRole('button', { name: `Edit ${name}`, exact: true })).toHaveCount(0);
    });

    await test.step('delete', async () => {
        await page.getByRole('button', { name: `Delete ${renamed}` }).click();
        await confirmDeletion(page);
        await expectToast(page, 'Category has been Deleted');
        await expect(rowWith(page, renamed)).toHaveCount(0);
    });
});

test('an administrator can create, edit and delete a tag', async ({ page }) => {
    const suffix = uniqueSuffix();
    const name = `e2e-tag-${suffix}`;
    const renamed = `e2e-tag-renamed-${suffix}`;

    await page.goto('/product/tag');
    await expect(page.getByRole('heading', { name: 'Tags', exact: true })).toBeVisible();

    await test.step('create', async () => {
        await page.getByRole('button', { name: 'Add new' }).click();
        const dialog = page.getByRole('dialog', { name: 'Create new tag' });
        await dialog.getByLabel('Name').fill(name);
        await dialog.getByRole('button', { name: 'Create' }).click();
        await expectToast(page, 'Tag Created Successfully');
        await expect(rowWith(page, name)).toBeVisible();
    });

    await test.step('edit', async () => {
        await page.getByRole('button', { name: `Edit ${name}` }).click();
        const dialog = page.getByRole('dialog', { name: 'Update tag' });
        await dialog.getByLabel('Name').fill(renamed);
        await dialog.getByRole('button', { name: 'Update' }).click();
        await expectToast(page, 'Tag Information has been updated');
        await expect(rowWith(page, renamed)).toBeVisible();
        await expect(page.getByRole('button', { name: `Edit ${name}`, exact: true })).toHaveCount(0);
    });

    await test.step('delete', async () => {
        await page.getByRole('button', { name: `Delete ${renamed}` }).click();
        await confirmDeletion(page);
        await expectToast(page, 'Tag has been Deleted');
        await expect(rowWith(page, renamed)).toHaveCount(0);
    });
});
