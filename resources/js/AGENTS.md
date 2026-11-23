# AGENTS.md: frontend (`resources/js/`)

Applies in addition to the [root AGENTS.md](../../AGENTS.md). Background:
[ARCHITECTURE.md](../../ARCHITECTURE.md) ("The dashboard (SPA)" and "The
SPA calling the API").

## Layout

- Vite entries (`vite.config.js`): `app.js` (the dashboard SPA), `auth.js`
  (styles and Bootstrap behaviour for the server-rendered guest pages) and
  `api-docs.js` (Swagger UI for `/docs`, kept out of the dashboard bundle).
- `routes.js` lists the SPA routes; `App.vue` holds the AdminLTE layout and
  the sidebar menu. Pages are in `components/` (`components/product/` for
  the catalogue, `components/passport/` for the Developer page).
- `HasError` and `PaginationNav` are registered globally in `app.js`; use
  them as `<has-error>` and `<pagination-nav>`.
- Page components use the Options API (`export default { data, methods }`),
  like `components/product/CategoriesPage.vue`. Small shared components in
  `components/common/` use `<script setup>`. Follow the style of the file
  you are in.

## Helpers in `lib/` and `bootstrap.js`

- `lib/Form.js` (`Form`): create and edit forms. Keeps only the declared
  fields and fills `form.errors` from a 422 response for `<has-error>`.
- `lib/progress.js` (`track()`): wrap every request so the progress bar
  shows.
- `lib/notify.js`: `toastSuccess()`, `toastError()`, `errorMessage(error)`,
  `confirmDelete()`, `showNotice()`.
- `lib/format.js`: `formatDate()`, `formatCurrency()`, `truncate()`,
  `storageUrl()` (safe image URLs).
- `lib/products.js`: product statuses, stock levels, sort options and
  `LOW_STOCK_THRESHOLD`. Keep them equal to `App\Enums\ProductStatus`,
  `App\Enums\StockLevel`, `ProductIndexRequest` and
  `Product::LOW_STOCK_THRESHOLD`.
- `lib/user.js` (`currentUser`): the signed-in user, read from `data-user`
  on `#spa-app`.
- `lib/passport.js`: error handling for Passport's JSON API on the
  Developer page.
- `bootstrap.js`: axios defaults and interceptor; `showModal()` and
  `hideModal()` for Bootstrap modals.

## Calling the API

- Use axios with same-origin, relative URLs (`/api/...`). Authentication is
  the HttpOnly `laravel_token` cookie plus the `X-CSRF-TOKEN` header that
  `bootstrap.js` sets. Never store tokens in `localStorage` or
  `sessionStorage`.
- Successful responses are `{success, data, message}`: read the payload from
  `response.data.data` and show `response.data.message` with
  `toastSuccess()`.
- Errors: `toastError(errorMessage(error, 'Unable to load ...'))`. 422
  errors appear next to the fields through `Form` and `<has-error>`.
- The interceptor already sends the browser to `/login` on 401 or 419 and to
  `/email/verify` on the "not verified" 403. Do not handle those again.
- `meta.admin` in `routes.js` and `$gate.isAdmin()` only hide UI. The API
  must enforce every rule on its own.

## Content-Security-Policy

The app sends `script-src 'self' 'nonce-...'` without `'unsafe-eval'` or
`'unsafe-inline'`.

- No inline `<script>` in Blade, no inline event handlers (`onclick=...`),
  no `v-html`, no `eval` or `new Function`.
- Vue runs as a runtime-only build: write templates in `.vue` files only,
  never as `template:` strings.
- Bundle everything with Vite from npm packages: no CDNs, remote fonts or
  third-party origins. Images use same-origin URLs (`storageUrl()`).
- SweetAlert2 renders `title` and `html` as HTML. Pass server or user text
  through `titleText` and `text`, as the helpers in `lib/notify.js` do.

## Adding a page

1. Add the component, following `components/product/CategoriesPage.vue`.
2. Add the route to `routes.js` (with `meta.title`, and `meta.admin` if
   only administrators should see it) and a menu entry in `App.vue`.
3. Add an E2E test, and add the path to the page list in
   `tests/e2e/security.spec.js` (see [tests/e2e/AGENTS.md](../../tests/e2e/AGENTS.md)).
4. Run `npm run lint`, `npm run format:check` and `npm run build`.

Prettier settings are in `.prettierrc.json` (4 spaces, single quotes,
120 columns). `npm run format` fixes formatting.
