import axios from 'axios';
import { Modal } from 'bootstrap';

// Same-origin requests only. Authentication relies on the httpOnly
// `laravel_token` cookie (Passport CreateFreshApiToken) plus the CSRF header;
// no token is ever read from or written to Web Storage.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common.Accept = 'application/json';

const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf;
}

// Exact message the API returns (HTTP 403) when the signed-in user has not
// verified their email address yet. Other 403 responses are ordinary
// authorization failures and are left to the calling component.
export const UNVERIFIED_EMAIL_MESSAGE = 'Your email address is not verified.';

// Full page load (not a router navigation) so the server decides what to show.
let redirecting = false;
function redirectTo(path) {
    if (redirecting) return;
    redirecting = true;
    window.location.assign(path);
}

// 401 = session/API cookie expired, 419 = CSRF token mismatch (stale page).
// In both cases the only safe recovery is a fresh login page load.
// 403 + UNVERIFIED_EMAIL_MESSAGE = send the user to the "verify your email" page.
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        if (status === 401 || status === 419) {
            redirectTo('/login');
        } else if (status === 403 && error.response.data?.message === UNVERIFIED_EMAIL_MESSAGE) {
            redirectTo('/email/verify');
        }
        return Promise.reject(error);
    },
);

export function showModal(element) {
    if (element) Modal.getOrCreateInstance(element).show();
}

export function hideModal(element) {
    if (element) Modal.getInstance(element)?.hide();
}
