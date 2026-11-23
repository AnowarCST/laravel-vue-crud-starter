import { randomBytes } from 'node:crypto';
import { deflateSync } from 'node:zlib';
import { expect } from '@playwright/test';
import { mailpitURL } from './env.js';

/** Unique, readable suffix for records created by a test run. */
export function uniqueSuffix() {
    return `${Date.now()}${randomBytes(2).toString('hex')}`;
}

/** Random password that meets the 12 character minimum. */
export function randomPassword() {
    return randomBytes(12).toString('hex');
}

/**
 * Sign in through the real login form. The app allows 10 login attempts per
 * minute per IP address, so starting a second full run within a minute of the
 * first one can hit that limit; fail with a clear message when it happens.
 */
export async function login(page, { email, password }) {
    await page.goto('/login');
    await page.getByLabel('E-Mail Address').fill(email);
    await page.getByLabel('Password').fill(password);
    const [response] = await Promise.all([
        page.waitForResponse((r) => r.request().method() === 'POST' && new URL(r.url()).pathname === '/login'),
        page.getByRole('button', { name: 'Sign In' }).click(),
    ]);
    if (response.status() === 429) {
        throw new Error(
            'The login rate limit (10 per minute per IP) was reached. Wait a minute and run the tests again.',
        );
    }
}

/**
 * Call the JSON API from inside the page, the same way the SPA does: with
 * the session's laravel_token cookie and the CSRF token from the meta tag.
 */
export function apiRequest(page, method, url, body) {
    return page.evaluate(
        async ({ method, url, body }) => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(url, {
                method,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: body === undefined ? undefined : JSON.stringify(body),
            });
            const text = await response.text();
            let json = null;
            try {
                json = JSON.parse(text);
            } catch {
                // Not JSON (for example an HTML error page); only the status matters then.
            }
            return { status: response.status, body: json };
        },
        { method, url, body },
    );
}

/**
 * Create a category through the API and return it. Tests create their own
 * categories and products, so they never depend on the demo data.
 */
export async function createCategory(page, name) {
    const response = await apiRequest(page, 'POST', '/api/category', { name, description: null });
    expect(response.status, `create category ${name}`).toBe(200);
    return response.body.data;
}

/**
 * Create a product through the API and return it. `name`, `sku` and
 * `category_id` must be given; the other fields have defaults here, and the
 * API defaults stock to 0 and status to "active".
 */
export async function createProduct(page, product) {
    const response = await apiRequest(page, 'POST', '/api/product', {
        description: 'Created by the end-to-end tests.',
        price: 10,
        tags: [],
        ...product,
    });
    expect(response.status, `create product ${product.name}`).toBe(200);
    return response.body.data;
}

/** Wait for the SweetAlert2 toast with this title. */
export async function expectToast(page, text) {
    await expect(page.locator('.swal2-toast .swal2-title')).toHaveText(text);
}

/** Confirm the SweetAlert2 "Delete ...?" dialog. */
export async function confirmDeletion(page) {
    const dialog = page.locator('.swal2-popup:not(.swal2-toast)');
    await expect(dialog).toBeVisible();
    await dialog.getByRole('button', { name: 'Yes, delete' }).click();
}

/** Table row that contains the given text. */
export function rowWith(page, text) {
    return page.getByRole('row').filter({ hasText: text });
}

/**
 * Newest Mailpit message sent to `address`, as returned by
 * GET /api/v1/message/{ID} (includes the Text and HTML bodies).
 */
export async function waitForEmail(request, address) {
    let id;
    await expect
        .poll(
            async () => {
                const response = await request.get(`${mailpitURL}/api/v1/messages`);
                if (!response.ok()) return undefined;
                const { messages = [] } = await response.json();
                id = messages.find((message) =>
                    (message.To || []).some((to) => to.Address.toLowerCase() === address.toLowerCase()),
                )?.ID;
                return id;
            },
            { message: `email to ${address} in Mailpit`, timeout: 15_000 },
        )
        .toBeTruthy();
    const response = await request.get(`${mailpitURL}/api/v1/message/${id}`);
    expect(response.ok()).toBeTruthy();
    return response.json();
}

// --- Small PNG encoder, so the upload test needs no binary fixture ---------

const CRC_TABLE = Array.from({ length: 256 }, (_, n) => {
    let c = n;
    for (let k = 0; k < 8; k += 1) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    return c >>> 0;
});

function crc32(buffer) {
    let crc = 0xffffffff;
    for (const byte of buffer) crc = CRC_TABLE[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    return (crc ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
    const length = Buffer.alloc(4);
    length.writeUInt32BE(data.length);
    const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(body));
    return Buffer.concat([length, body, crc]);
}

/** Solid-colour RGB PNG image. */
export function makePng(width = 64, height = 48, [r, g, b] = [13, 110, 253]) {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header[8] = 8; // bit depth
    header[9] = 2; // colour type: RGB
    const row = Buffer.alloc(1 + width * 3);
    for (let x = 0; x < width; x += 1) row.set([r, g, b], 1 + x * 3);
    const pixels = Buffer.concat(Array.from({ length: height }, () => row));
    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(pixels)),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}
