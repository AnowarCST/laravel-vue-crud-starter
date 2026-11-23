import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';
import { createCategory, createProduct, rowWith, uniqueSuffix } from './support/helpers.js';

// Runs as the regular user: the dashboard makes fewer API requests for a
// user than for an administrator, which keeps the run under the rate limit.
test.use({ storageState: storageState.user });

/** The number at the start of a badge such as "3 low" or "1 out". */
async function badgeCount(link) {
    await expect(link).toHaveText(/^\s*\d+ (low|out)\s*$/);
    return Number.parseInt((await link.textContent()).trim(), 10);
}

test('the dashboard counts products and links to the low and out of stock lists', async ({ page }) => {
    const suffix = uniqueSuffix();
    const lowName = `E2E Low Stock ${suffix}`;
    const outName = `E2E Out Of Stock ${suffix}`;

    const productsBox = page.locator('.small-box').filter({ hasText: 'Products' });
    const stockCard = page
        .locator('.card')
        .filter({ has: page.getByRole('heading', { name: 'Low stock', exact: true }) });
    const lowLink = stockCard.getByRole('link', { name: /low stock: show products$/ });
    const outLink = stockCard.getByRole('link', { name: /out of stock: show products$/ });

    // The demo data and earlier tests decide the starting numbers, so the
    // test only checks that they go up by the products it creates.
    await page.goto('/dashboard');
    await expect(productsBox.locator('h3')).toHaveText(/^\d+$/);
    const before = {
        total: Number(await productsBox.locator('h3').textContent()),
        low: await badgeCount(lowLink),
        out: await badgeCount(outLink),
    };

    const category = await createCategory(page, `E2E Dashboard Category ${suffix}`);
    await createProduct(page, {
        name: lowName,
        sku: `E2E-LOW-${suffix}`.toUpperCase(),
        stock: 2,
        category_id: category.id,
    });
    await createProduct(page, {
        name: outName,
        sku: `E2E-OUT-${suffix}`.toUpperCase(),
        stock: 0,
        category_id: category.id,
    });

    await test.step('the counts include the new products', async () => {
        await page.reload();
        await expect(productsBox.locator('h3')).toHaveText(String(before.total + 2));
        await expect(lowLink).toHaveText(`${before.low + 1} low`);
        await expect(outLink).toHaveText(`${before.out + 1} out`);
    });

    await test.step('the low stock list only shows products with 5 units or fewer', async () => {
        const badges = stockCard.locator('.list-group-item .badge');
        await expect(badges.first()).toBeVisible();
        for (const text of await badges.allTextContents()) {
            expect(text.trim()).toMatch(/^(Out of stock|[1-5] left)$/);
        }
    });

    // The products list is newest first, so the new products are on page 1.
    await test.step('the "low" badge opens the low stock products', async () => {
        await expect(outLink).toHaveAttribute('href', '/products?stock=out');
        await lowLink.click();
        await expect(page).toHaveURL(/\/products\?stock=low$/);
        const filters = page.getByRole('search', { name: 'Filter products' });
        await expect(filters.getByLabel('Stock')).toHaveValue('low');
        await expect(rowWith(page, lowName)).toBeVisible();
        await expect(rowWith(page, outName)).toHaveCount(0);
    });
});
