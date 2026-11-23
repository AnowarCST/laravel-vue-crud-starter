import { defineConfig, devices } from '@playwright/test';
import { baseURL } from './tests/e2e/support/env.js';

// End-to-end tests. They need the stack from tests/e2e/setup/start-stack.sh;
// see README.md ("End-to-end tests").
//
// Tests run one at a time: they share one database, and the app's rate limits
// (10 login attempts per minute per IP, 60 API requests per minute per user)
// apply to the test run too. The suite signs in through the login form 8
// times; most tests reuse the sessions saved by auth.setup.js instead.
const chromium = {
    ...devices['Desktop Chrome'],
    viewport: { width: 1366, height: 900 },
};

const projects = [
    { name: 'setup', testMatch: /auth\.setup\.js$/ },
    { name: 'chromium', use: chromium, dependencies: ['setup'] },
];

export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: 'tests/e2e/test-results',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: 'tests/e2e/playwright-report', open: 'never' }]],
    use: {
        baseURL,
        locale: 'en-US',
        timezoneId: 'UTC',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects,
});
