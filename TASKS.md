# Tasks (Modernisation + Docker + QA)

## Immediate
- [ ] Add `docker-compose.yml` with `app`, `node`, `db` services; confirm ports `8000`, `5173`, `3306`.
- [ ] Ensure `.env` DB values match compose (`DB_HOST=db`, `DB_DATABASE=laravel`, `DB_USERNAME=laravel`, `DB_PASSWORD=secret`).
- [ ] `composer install` in app container; `npm install` in node container; `php artisan key:generate`.
- [ ] Verify endpoints: API `http://localhost:8000`, Vite `http://localhost:5173`.

## Framework Upgrades
- [ ] Upgrade to Laravel 11; add `laravel/pint`, `pestphp/pest`, `nunomaduro/larastan`.
- [ ] Migrate to Vite; Vue 3 + Router 4 + Pinia; drop Mix.
- [ ] Switch to Sanctum by default; keep Passport as optional preset.

## Security & Quality
- [ ] Add `spatie/laravel-csp`, enable basic CSP; tighten CORS; add rate limits.
- [ ] Add OpenAPI generator (`l5-swagger` or alternative) and commit `openapi.yaml`.
- [ ] Add CI: GitHub Actions for Pest, Pint, Larastan, ESLint/Prettier, build.
- [ ] Add DAST job with OWASP ZAP against local compose.

## DX & Docs
- [ ] Write/refresh `README.md`, `ARCHITECTURE.md`, `API.md`, `CONTEXT.md`.
- [ ] Add `CONTRIBUTING.md`, link to `CHECKLIST.md`, `RULES.md`, `CODE_OF_CONDUCT.md`.
- [ ] Enable Dependabot and CodeQL.

## Testing
- [ ] Convert/augment tests to Pest; target meaningful coverage on CRUD modules.
- [ ] Add Playwright API/UI smoke flows (login, basic CRUD).

## UI
- [ ] Move from AdminLTE/Bootstrap to Tailwind + Headless UI (or PrimeVue).
- [ ] Create reusable table/form components; a11y checks; empty states.
