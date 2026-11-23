import { test, expect } from './support/test.js';
import { storageState } from './support/env.js';
import { apiRequest, expectToast, login, randomPassword, uniqueSuffix } from './support/helpers.js';

// Each test signs in as its own fresh account, created by the administrator,
// because changing a password affects every session of that account.
async function createAccount(browser) {
    const suffix = uniqueSuffix();
    const account = {
        name: `E2E Profile ${suffix}`,
        email: `e2e-profile-${suffix}@example.test`,
        password: randomPassword(),
    };
    const adminContext = await browser.newContext({ storageState: storageState.admin2 });
    const adminPage = await adminContext.newPage();
    // Any signed-in page works; this one makes only one rate-limited API call.
    await adminPage.goto('/developer');
    const response = await apiRequest(adminPage, 'POST', '/api/user', { ...account, type: 'user' });
    await adminContext.close();
    expect(response.status, 'create the test account').toBe(200);
    return account;
}

test('a user can update their name', async ({ browser, page }) => {
    const account = await createAccount(browser);
    const newName = `${account.name} Updated`;
    await login(page, account);
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/profile');
    await expect(page.getByLabel('Name')).toHaveValue(account.name);
    await expect(page.getByLabel('Email')).toHaveValue(account.email);

    await page.getByLabel('Name').fill(newName);
    await page.getByRole('button', { name: 'Save profile' }).click();

    await expectToast(page, 'Profile has been updated');
    await expect(page.locator('.app-header')).toContainText(newName);

    await page.reload();
    await expect(page.getByLabel('Name')).toHaveValue(newName);
});

test('changing the password requires the current password', async ({ browser, page }) => {
    const account = await createAccount(browser);
    await login(page, account);
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/profile');

    const newPassword = randomPassword();
    const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Change password' }) });

    await test.step('a wrong current password is rejected', async () => {
        await form.getByLabel('Current password').fill('this-is-not-my-password');
        await form.getByLabel('New password', { exact: true }).fill(newPassword);
        await form.getByLabel('Confirm new password').fill(newPassword);
        await form.getByRole('button', { name: 'Change password' }).click();

        await expect(form.getByText('The current password is incorrect.')).toBeVisible();
        await expect(form.getByLabel('Current password')).toHaveClass(/is-invalid/);
    });

    await test.step('the correct current password changes it', async () => {
        await form.getByLabel('Current password').fill(account.password);
        await form.getByRole('button', { name: 'Change password' }).click();

        await expectToast(page, 'Password Has been updated');
        await expect(form.getByLabel('Current password')).toHaveValue('');
    });

    // Only the new password is tried here: every login attempt counts against
    // the per-IP login rate limit, which the whole suite shares.
    await test.step('the new password can be used to sign in', async () => {
        await page.context().clearCookies();
        await login(page, { ...account, password: newPassword });
        await expect(page).toHaveURL(/\/dashboard$/);
    });
});

// Known bug, kept visible on purpose. SECURITY.md and bootstrap/app.php say a
// password change signs out the account's *other* sessions. In practice the
// session that made the change is signed out too: the change goes through the
// API guard, so the web session keeps the old password hash, and Laravel's
// AuthenticateSession middleware logs it out on the next page load.
// When this is fixed, the test passes, Playwright reports an unexpected pass,
// and the test.fail() line should be removed.
test('changing the password keeps the current session signed in', async ({ browser, page }) => {
    test.fail(true, 'Known bug: changing your own password signs out the current session as well');

    const account = await createAccount(browser);
    await login(page, account);
    await expect(page).toHaveURL(/\/dashboard$/);
    await page.goto('/profile');

    const newPassword = randomPassword();
    await page.getByLabel('Current password').fill(account.password);
    await page.getByLabel('New password', { exact: true }).fill(newPassword);
    await page.getByLabel('Confirm new password').fill(newPassword);
    await page.getByRole('button', { name: 'Change password' }).click();
    await expectToast(page, 'Password Has been updated');

    await page.reload();
    await expect(page).toHaveURL(/\/profile$/, { timeout: 5_000 });
});
