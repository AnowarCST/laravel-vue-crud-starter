import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';
import { apiRequest, confirmDeletion, expectToast, makePng, rowWith, uniqueSuffix } from './support/helpers.js';

test.use({ storageState: storageState.admin });

test('an administrator can create, edit and delete a product with a category, tags and a photo', async ({ page }) => {
    const suffix = uniqueSuffix();
    const categoryName = `E2E Product Category ${suffix}`;
    const name = `E2E Product ${suffix}`;
    const renamed = `E2E Product Renamed ${suffix}`;
    const tags = [`e2e-new-${suffix}`, `e2e-second-${suffix}`];

    // The test creates its own category instead of relying on demo data.
    await page.goto('/products');
    const created = await apiRequest(page, 'POST', '/api/category', { name: categoryName, description: null });
    expect(created.status).toBe(200);
    await page.reload();

    await test.step('create', async () => {
        await page.getByRole('button', { name: 'Add new' }).click();
        const dialog = page.getByRole('dialog', { name: 'Create new product' });
        await dialog.getByLabel('Name').fill(name);
        await dialog.getByLabel('Description').fill('A product created by the end-to-end tests.');
        await dialog.getByLabel('Price').fill('1234.5');
        await dialog.getByLabel('Category').selectOption({ label: categoryName });

        for (const tag of tags) {
            await dialog.getByLabel('Tags').fill(tag);
            await dialog.getByLabel('Tags').press('Enter');
            await expect(dialog.getByRole('button', { name: `Remove tag ${tag}` })).toBeVisible();
        }

        await dialog.getByLabel('Photo').setInputFiles({
            name: 'e2e-photo.png',
            mimeType: 'image/png',
            buffer: makePng(64, 48),
        });
        const preview = dialog.getByRole('img', { name: 'Product photo preview' });
        await expect(preview).toHaveAttribute('src', /\/storage\/uploads\/[A-Za-z0-9]+\.png$/);

        await dialog.getByRole('button', { name: 'Create' }).click();
        await expectToast(page, 'Product Created Successfully');
        await expect(dialog).toBeHidden();
    });

    const row = () => rowWith(page, suffix);

    await test.step('the list shows the new product, its category, tags and photo', async () => {
        await expect(row()).toContainText(name);
        await expect(row()).toContainText(categoryName);
        await expect(row()).toContainText('$1,234.50');
        for (const tag of tags) await expect(row()).toContainText(tag);

        const photo = row().locator('img.product-thumb');
        await expect(photo).toHaveAttribute('src', /\/storage\/uploads\/[A-Za-z0-9]+\.png$/);
        await expect.poll(() => photo.evaluate((img) => img.complete && img.naturalWidth)).toBe(64);

        // The stored file is the server's re-encoded copy, served as an image.
        const response = await page.request.get((await photo.getAttribute('src')) || '');
        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toBe('image/png');
        expect(response.headers()['x-content-type-options']).toBe('nosniff');
    });

    await test.step('edit', async () => {
        await page.getByRole('button', { name: `Edit ${name}` }).click();
        const dialog = page.getByRole('dialog', { name: 'Update product' });
        await expect(dialog.getByLabel('Name')).toHaveValue(name);
        await expect(dialog.getByLabel('Category')).toHaveValue(String(created.body.data.id));
        await expect(dialog.getByRole('img', { name: 'Product photo preview' })).toBeVisible();

        await dialog.getByLabel('Name').fill(renamed);
        await dialog.getByLabel('Price').fill('99');
        await dialog.getByRole('button', { name: `Remove tag ${tags[1]}` }).click();
        await dialog.getByRole('button', { name: 'Update' }).click();

        await expectToast(page, 'Product Information has been updated');
        await expect(row()).toContainText(renamed);
        await expect(row()).toContainText('$99.00');
        await expect(row()).toContainText(tags[0]);
        await expect(row()).not.toContainText(tags[1]);
        await expect(row().locator('img.product-thumb')).toBeVisible();
    });

    await test.step('delete', async () => {
        await page.getByRole('button', { name: `Delete ${renamed}` }).click();
        await confirmDeletion(page);
        await expectToast(page, 'Product has been Deleted');
        await expect(row()).toHaveCount(0);
    });
});

test('a file that is not an image is rejected', async ({ page }) => {
    await page.goto('/products');
    await page.getByRole('button', { name: 'Add new' }).click();
    const dialog = page.getByRole('dialog', { name: 'Create new product' });

    // Declared as a PNG, so it passes the browser-side type check and the
    // server has to reject it.
    await dialog.getByLabel('Photo').setInputFiles({
        name: 'not-an-image.png',
        mimeType: 'image/png',
        buffer: Buffer.from('<?php echo "hello"; ?>'),
    });
    await expect(dialog.locator('.invalid-feedback')).toContainText(/image/i);
    await expect(dialog.getByRole('img', { name: 'Product photo preview' })).toHaveCount(0);
});

test.describe('as a regular user', () => {
    test.use({ storageState: storageState.user });

    test('can edit products but not delete them', async ({ browser, page }) => {
        const suffix = uniqueSuffix();
        const name = `E2E User Product ${suffix}`;

        // Test data is created by the administrator through the API.
        const adminContext = await browser.newContext({ storageState: storageState.admin });
        const adminPage = await adminContext.newPage();
        await adminPage.goto('/developer');
        const category = await apiRequest(adminPage, 'POST', '/api/category', { name: `E2E Category ${suffix}` });
        const product = await apiRequest(adminPage, 'POST', '/api/product', {
            name,
            description: 'Created for the permission test.',
            price: 10,
            category_id: category.body?.data?.id,
            tags: [],
        });
        await adminContext.close();
        expect(product.status, 'create the test product').toBe(200);

        await page.goto('/products');
        await expect(rowWith(page, name)).toBeVisible();
        await expect(page.getByRole('button', { name: `Edit ${name}` })).toBeVisible();
        await expect(page.getByRole('button', { name: `Delete ${name}` })).toHaveCount(0);

        const response = await apiRequest(page, 'DELETE', `/api/product/${product.body.data.id}`);
        expect(response.status).toBe(403);
    });
});
