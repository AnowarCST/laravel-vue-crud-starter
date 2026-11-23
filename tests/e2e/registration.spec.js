import { test, expect } from './support/test.js';
import { apiRequest, randomPassword, uniqueSuffix, waitForEmail } from './support/helpers.js';

test('a new account must verify its email address before using the app', async ({ page, request }) => {
    const suffix = uniqueSuffix();
    const name = `E2E Registered ${suffix}`;
    const email = `e2e-register-${suffix}@example.test`;
    const password = randomPassword();

    await test.step('register', async () => {
        await page.goto('/register');
        await page.getByLabel('Name').fill(name);
        await page.getByLabel('E-Mail Address').fill(email);
        await page.getByLabel('Password', { exact: true }).fill(password);
        await page.getByLabel('Confirm Password').fill(password);
        await page.getByRole('button', { name: 'Register' }).click();
    });

    await test.step('an unverified account only sees the verification page', async () => {
        await expect(page).toHaveURL(/\/email\/verify$/);
        await expect(page.getByText('Verify Your Email Address')).toBeVisible();
        await expect(page.getByText(email)).toBeVisible();

        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/email\/verify$/);

        const response = await apiRequest(page, 'GET', '/api/profile');
        expect(response.status).toBe(403);
        expect(response.body?.message).toBe('Your email address is not verified.');
    });

    await test.step('the link in the verification email opens the dashboard', async () => {
        const message = await waitForEmail(request, email);
        expect(message.Subject).toBe('Verify your email address');

        const link = message.Text.match(/https?:\/\/\S+\/email\/verify\/\S+/)?.[0];
        expect(link, 'verification link in the email body').toBeTruthy();

        await page.goto(link);
        await expect(page).toHaveURL(/\/dashboard(\?verified=1)?$/);
        await expect(page.getByRole('heading', { name: 'Dashboard', exact: true })).toBeVisible();
        await expect(page.locator('.app-header')).toContainText(name);

        // A self-registered account is a regular user, never an administrator.
        await expect(page.getByRole('link', { name: 'Users' })).toHaveCount(0);
        const response = await apiRequest(page, 'GET', '/api/profile');
        expect(response.status).toBe(200);
        expect(response.body?.data?.type).toBe('user');
    });
});
