import { test as base, expect } from '@playwright/test';

/**
 * Collect Content-Security-Policy violations and uncaught JavaScript errors
 * from every page in a browser context.
 *
 * CSP violations are reported twice by Chromium: as a `securitypolicyviolation`
 * DOM event and as a console error. Both are recorded, so a violation is
 * caught even if one of the two channels changes.
 */
export async function watchBrowserIssues(context, issues) {
    await context.exposeBinding('__e2eReportCspViolation', ({ page }, violation) => {
        issues.push({ kind: 'csp-event', url: page.url(), ...violation });
    });
    await context.addInitScript(() => {
        document.addEventListener('securitypolicyviolation', (event) => {
            window.__e2eReportCspViolation({
                directive: event.effectiveDirective,
                blockedURI: event.blockedURI,
                sourceFile: event.sourceFile,
                line: event.lineNumber,
                sample: event.sample,
            });
        });
    });
    context.on('console', (message) => {
        if (message.type() === 'error' && /content security policy/i.test(message.text())) {
            issues.push({ kind: 'csp-console', url: message.page()?.url(), text: message.text() });
        }
    });
    context.on('weberror', (webError) => {
        issues.push({ kind: 'page-error', url: webError.page()?.url(), text: String(webError.error()) });
    });
}

export const test = base.extend({
    // Every test fails if any page it opened logged a CSP violation or an
    // uncaught error. Contexts a test creates itself are checked when it
    // passes them to `watch`.
    browserIssues: [
        async ({ context }, use) => {
            const issues = [];
            await watchBrowserIssues(context, issues);
            await use({ issues, watch: (other) => watchBrowserIssues(other, issues) });
            expect(issues, 'CSP violations or uncaught page errors').toEqual([]);
        },
        { auto: true },
    ],
});

export { expect };
