import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/** File written by tests/e2e/setup/start-stack.sh (gitignored). */
export const STACK_ENV_FILE = fileURLToPath(new URL('../.stack/stack.env', import.meta.url));

/**
 * Copy KEY=VALUE lines from the stack file into process.env. Variables that
 * are already set win, so the Docker runner can point Mailpit at
 * http://mailpit:8025 inside the app's network namespace.
 */
export function loadStackEnv(file = STACK_ENV_FILE) {
    if (!existsSync(file)) return;
    for (const line of readFileSync(file, 'utf8').split('\n')) {
        const match = line.match(/^([A-Z0-9_]+)=(.*)$/);
        if (!match) continue;
        const [, key, raw] = match;
        const value = raw.replace(/^"(.*)"$/, '$1');
        if (process.env[key] === undefined) process.env[key] = value;
    }
}

function required(name) {
    const value = process.env[name];
    if (!value) {
        throw new Error(`${name} is not set. Start the stack first: tests/e2e/setup/start-stack.sh`);
    }
    return value;
}

loadStackEnv();

export const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8080';
export const mailpitURL = process.env.E2E_MAILPIT_URL || 'http://localhost:8125';

function account(prefix) {
    return {
        name: required(`${prefix}_NAME`),
        email: required(`${prefix}_EMAIL`),
        password: required(`${prefix}_PASSWORD`),
    };
}

/**
 * Accounts created by start-stack.sh, read lazily so `playwright test --list`
 * works without a stack. There are two administrators because the API allows
 * 60 requests per minute per user, and one account would reach that limit
 * during a full run.
 */
export const accounts = {
    get admin() {
        return account('E2E_ADMIN');
    },
    get admin2() {
        return account('E2E_ADMIN2');
    },
    get user() {
        return account('E2E_USER');
    },
};

/** Saved sign-in state per account, written by auth.setup.js. */
export const storageState = Object.fromEntries(
    ['admin', 'admin2', 'user'].map((role) => [
        role,
        fileURLToPath(new URL(`../.stack/${role}.json`, import.meta.url)),
    ]),
);
