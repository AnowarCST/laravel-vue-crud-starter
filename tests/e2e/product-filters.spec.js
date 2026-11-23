import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';
import { createCategory, createProduct, uniqueSuffix } from './support/helpers.js';

// Regular users can read and create products, so this test runs as the
// "user" account and leaves the administrators' API rate limit to the
// tests that need an administrator.
test.use({ storageState: storageState.user });

test('the products page searches, filters and sorts, and keeps the filters in the URL', async ({ page }) => {
    const suffix = uniqueSuffix();
    const categoryName = `E2E Filter Category ${suffix}`;
    const tagName = `e2e-filter-${suffix}`;
    const apple = `E2E Apple ${suffix}`;
    const banana = `E2E Banana ${suffix}`;
    const cherry = `E2E Cherry ${suffix}`;
    const cherrySku = `E2E-C-${suffix}`.toUpperCase();

    // Three products in a new category, created oldest to newest. Filtering
    // by this category shows only them, whatever else is in the database.
    await page.goto('/developer');
    const category = await createCategory(page, categoryName);
    const common = { category_id: category.id };
    await createProduct(page, {
        ...common,
        name: apple,
        sku: `E2E-A-${suffix}`.toUpperCase(),
        price: 30,
        stock: 0,
        status: 'active',
    });
    await createProduct(page, {
        ...common,
        name: banana,
        sku: `E2E-B-${suffix}`.toUpperCase(),
        price: 10,
        stock: 3,
        status: 'draft',
        tags: [{ text: tagName }],
    });
    await createProduct(page, { ...common, name: cherry, sku: cherrySku, price: 20, stock: 50, status: 'archived' });

    const filters = page.getByRole('search', { name: 'Filter products' });
    const names = page.locator('tbody .product-name-cell .fw-semibold');
    const inUrl = (key, value) => new RegExp(`[?&]${key}=${encodeURIComponent(value)}(&|$)`);

    // Each filter change is one API request. The API allows 60 requests per
    // minute per account, so the steps below avoid changes that only repeat
    // what an earlier step already showed.
    await test.step('a filter in the URL is applied when the page opens', async () => {
        await page.goto(`/products?category_id=${category.id}`);
        await expect(filters.getByLabel('Category')).toHaveValue(String(category.id));
        // Newest first by default.
        await expect(names).toHaveText([cherry, banana, apple]);
    });

    await test.step('sort', async () => {
        await filters.getByLabel('Sort by').selectOption('price');
        await expect(page).toHaveURL(inUrl('sort', 'price'));
        await expect(names).toHaveText([banana, cherry, apple]);

        await filters.getByLabel('Sort by').selectOption('-price');
        await expect(page).toHaveURL(inUrl('sort', '-price'));
        await expect(names).toHaveText([apple, cherry, banana]);
    });

    await test.step('filter by status and stock level', async () => {
        await filters.getByLabel('Status').selectOption('draft');
        await expect(page).toHaveURL(inUrl('status', 'draft'));
        await expect(names).toHaveText([banana]);

        // Filters are combined: no draft product is out of stock.
        await filters.getByLabel('Stock').selectOption('out');
        await expect(page).toHaveURL(inUrl('stock', 'out'));
        await expect(page.getByText('No products match these filters.')).toBeVisible();
        await expect(names).toHaveCount(0);

        await filters.getByLabel('Status').selectOption({ label: 'All' });
        await expect(page).not.toHaveURL(/[?&]status=/);
        await expect(names).toHaveText([apple]);

        await filters.getByLabel('Stock').selectOption('low');
        await expect(names).toHaveText([banana]);

        // Still sorted by price, highest first.
        await filters.getByLabel('Stock').selectOption('in');
        await expect(names).toHaveText([cherry, banana]);
    });

    await test.step('filter by tag', async () => {
        await filters.getByLabel('Tag').selectOption({ label: tagName });
        await expect(names).toHaveText([banana]);
    });

    await test.step('reset clears every filter', async () => {
        await filters.getByRole('button', { name: 'Reset' }).click();
        await expect(page).toHaveURL(/\/products$/);
        await expect(filters.getByLabel('Category')).toHaveValue('');
        await expect(filters.getByLabel('Tag')).toHaveValue('');
        await expect(filters.getByRole('button', { name: 'Reset' })).toBeDisabled();
    });

    await test.step('search matches the SKU, ignoring case', async () => {
        await filters.getByLabel('Search', { exact: true }).fill(cherrySku.toLowerCase());
        await expect(page).toHaveURL(inUrl('search', cherrySku.toLowerCase()));
        await expect(names).toHaveText([cherry]);
    });

    await test.step('search matches the name, and the filters survive a reload', async () => {
        await filters.getByLabel('Search', { exact: true }).fill(suffix);
        await expect(page).toHaveURL(inUrl('search', suffix));
        await expect(names).toHaveText([cherry, banana, apple]);

        await filters.getByLabel('Status').selectOption('archived');
        await expect(names).toHaveText([cherry]);

        await page.reload();
        await expect(filters.getByLabel('Search', { exact: true })).toHaveValue(suffix);
        await expect(filters.getByLabel('Status')).toHaveValue('archived');
        await expect(names).toHaveText([cherry]);
    });
});
