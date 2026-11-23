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

// 401 = session/API cookie expired, 419 = CSRF token mismatch (stale page).
// In both cases the only safe recovery is a fresh login page load.
let redirecting = false;
axios.interceptors.response.use(
    response => response,
    error => {
        const status = error.response?.status;
        if ((status === 401 || status === 419) && !redirecting) {
            redirecting = true;
            window.location.assign('/login');
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

export default axios;
