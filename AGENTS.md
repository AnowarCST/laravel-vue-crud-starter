# AGENTS.md

Instructions for AI coding assistants (and humans) working in this
repository. This file is the single source of truth for those instructions;
`CLAUDE.md`, `.cursor/rules/` and the scoped `AGENTS.md` files point here
(see [docs/ai-assistants.md](docs/ai-assistants.md)). For depth, read
[ARCHITECTURE.md](ARCHITECTURE.md), [CONTRIBUTING.md](CONTRIBUTING.md),
[SECURITY.md](SECURITY.md), [docs/ci-cd.md](docs/ci-cd.md) and
[docs/api/openapi.yaml](docs/api/openapi.yaml).

## Project

A Laravel + Vue 3 + AdminLTE single-page application starter: session login
with required email verification, an OAuth2 JSON API, and CRUD screens for
users, products, categories and tags. It is public and meant to be copied,
so the code must stay simple, secure by default and fully tested.

Stack (installed versions): PHP 8.5, Laravel 13.43, Passport 13.12,
laravel/ui 4.6, Vue 3.5, Vue Router 5.5, Vite 8.3, AdminLTE 4.10,
Bootstrap 5.3, MySQL 9.7 LTS, Node 26 LTS (build tooling only). Quality:
Pint, Larastan level 9, PHPUnit 13, ESLint 10, Prettier 3, Playwright 1.65,
OWASP ZAP.

## Scoped instructions

Read the nearest one before you change files in these folders:

- [resources/js/AGENTS.md](resources/js/AGENTS.md): Vue, AdminLTE, API calls, CSP.
- [tests/e2e/AGENTS.md](tests/e2e/AGENTS.md): Playwright tests and their stack.
- [database/AGENTS.md](database/AGENTS.md): migrations, factories, seeders.

## Commands

The development stack runs in Docker (`docker compose up -d`; app on
<http://localhost:8000>, Mailpit on <http://localhost:8025>, Vite dev server
with `docker compose --profile dev up -d node` on port 5173). PHP is not
expected on the host: run every PHP command in the `app` container. Use
`-T` when there is no terminal (scripts, agents).

| Task | Command |
| --- | --- |
| PHP code style (check / fix) | `docker compose exec -T app composer lint` / `docker compose exec -T app composer format` |
| PHP static analysis | `docker compose exec -T app composer analyse` |
| PHP tests (all) | `docker compose exec -T app php artisan test` |
| PHP tests (some) | `docker compose exec -T app php artisan test --filter=ProductListTest` or `... php artisan test tests/Feature/Api/ResponseFieldsTest.php` or `--testsuite=Unit` |
| JS/Vue lint (check / fix) | `npm run lint` / `npm run lint:fix` |
| JS formatting (check / fix) | `npm run format:check` / `npm run format` |
| Production build | `npm run build` |
| Dependency audits | `docker compose exec -T app composer audit` and `npm audit` |
| OpenAPI lint | `docker run --rm -e REDOCLY_TELEMETRY=off -v "$PWD/docs/api":/spec:ro -w /spec redocly/cli:2.82.0 lint openapi.yaml` |
| E2E tests | `tests/e2e/setup/start-stack.sh`, then `tests/e2e/setup/run-tests.sh [files]`, then `tests/e2e/setup/stop-stack.sh` |

The `npm` commands need Node 26 on the host. Without it, prefix them with
`docker compose --profile dev run --rm node`, for example
`docker compose --profile dev run --rm node npm run lint`.

Tool allowlists such as `.claude/settings.json` pre-approve only the exact
commands above without extra arguments. Filtered test runs (`--filter ...`)
and other variants need normal approval.

PHP tests always use in-memory SQLite (forced in `phpunit.xml`) and never
touch the MySQL data. The E2E stack is a separate throwaway stack on ports
8080 and 8125.

## Where things are

| Path | Contents |
| --- | --- |
| `app/Http/Controllers/Api/V1/` | Thin API controllers |
| `app/Http/Requests/<Area>/` | Form Requests: validation and authorization |
| `app/Actions/<Area>/` | Single-purpose classes for multi-step writes |
| `app/Http/Resources/`, `app/Http/Responses/ApiResponse.php` | Response fields and the response envelope |
| `app/Models/`, `app/Policies/`, `app/Enums/` | Models with `#[UsePolicy]`, one policy per model, backed enums |
| `app/Providers/AppServiceProvider.php` | Rate limiters, Passport settings, gates, password rule |
| `app/Http/Middleware/SecurityHeaders.php` | CSP and other security headers |
| `bootstrap/app.php` | Middleware order, exception rendering |
| `routes/api.php`, `routes/web.php` | API routes (prefix `/api`), web and SPA routes |
| `resources/js/` | Vue SPA, the `/docs` viewer, guest-page scripts |
| `resources/views/` | Blade views (SPA shell, auth pages) |
| `database/` | Migrations, factories, demo seeders |
| `tests/Feature/<Area>/`, `tests/Unit/`, `tests/Concerns/` | PHPUnit tests and shared helpers |
| `tests/e2e/` | Playwright tests and the E2E stack scripts |
| `docs/` | OpenAPI document, installation, testing, CI/CD, upgrading |
| `docker/`, `docker-compose.yml` | Dev and prod images, local stack |
| `.github/workflows/`, `deploy/` | CI and disabled deployment templates |

## Backend conventions

Follow the existing modules; `ProductController`, `ProductRequest`,
`ProductPolicy` and `CategoryRequest` are the reference.

- **Validation and authorization** live in Form Requests
  (`app/Http/Requests/<Area>/`). `authorize()` asks the policy (`update` for
  the bound model, otherwise `create`). Give every string a `max`, validate
  types strictly, use `Rule::enum()` for enum fields and
  `Password::defaults()` for passwords. Save only validated input
  (`$request->validated()` or `$request->safe()`).
- **Policies** are linked with `#[UsePolicy(XPolicy::class)]` on the model,
  not registered in a provider. Delete is authorized in the controller with
  `$this->authorize('delete', $model)`.
- **Actions**: writes that take more than one step (transaction, related
  records, files) go into a `final` class in `app/Actions/<Area>/` with a
  `handle()` method, injected into the controller method.
- **Responses**: return `ApiResponse::success($data, 'Message')`, which
  renders `{"success": true, "data": ..., "message": "..."}`. Serialize
  records with a JsonResource that lists every field. Lists use
  `->paginate(10)->through(fn (X $x) => new XResource($x))`.
- **Enums and casts**: backed enums in `app/Enums/` (`UserType`,
  `ProductStatus`, `StockLevel`), cast in the model's `casts()` method.
- **Query scopes** use the `#[Scope]` attribute on protected model methods
  (see `Product::search()`); controllers call `$query->search(...)`.
- **Current user**: inject it with `#[CurrentUser] User $user`
  (see `ProfileController`).
- **Route model binding** for categories, products and tags, so unknown ids
  return 404. Exception: `UserController` takes the id and checks
  `viewAny` before the lookup, so non-administrators get 403 and cannot
  probe which ids exist. Keep this "authorize before lookup" rule for users.
- **Rate limiters** are named in `AppServiceProvider`: `api` (60/min per
  account or IP), `login` (10/min per IP), `register` (5/min),
  `password-reset` (5/min per IP, 10/hour per email). Do not raise them to
  make a test pass.

## API contract

- Every `/api` change must update [docs/api/openapi.yaml](docs/api/openapi.yaml)
  in the same commit. `OpenApiDocumentTest` fails if a route is missing from
  the document or the document lists an operation without a route. Lint the
  document with the Redocly command above.
- `tests/Feature/Api/ResponseFieldsTest.php` pins the exact fields of every
  response. Adding, removing or renaming a field means updating that test,
  the resource and the OpenAPI document together.
- Every authenticated `/api` route sits inside the `auth:api` + `verified`
  group in `routes/api.php`. `EmailVerificationTest` checks this.

## Security rules

- Never commit secrets, real credentials or personal data (code, tests,
  fixtures, screenshots, commit messages). `.env` is ignored;
  `.env.example` holds empty placeholders only.
- Never seed or hard-code privileged accounts or default passwords. The
  first administrator is created by a person with
  `php artisan app:create-admin` (interactive).
- `users.type` is not mass assignable; set it only through
  `App\Actions\Users\CreateUser` or `forceFill` as the existing code does.
- Uploads go through `App\Actions\Products\StoreProductImage`, which
  re-encodes the image and picks a random name. Never store uploaded bytes
  directly or trust the client file name.
- Frontend code must work under the nonce-based CSP: no inline scripts, no
  inline event handlers, no `v-html`, no CDNs or third-party origins.
- More in [SECURITY.md](SECURITY.md). Report vulnerabilities privately,
  never in a public issue.

## Tests

- Put feature tests in `tests/Feature/<Area>/` (`Api/<Resource>/`, `Auth/`,
  `Security/`, `Docs/`, `Database/`, `Web/`) and pure unit tests in
  `tests/Unit/`.
- Use the helpers from `tests/Concerns/` (through `Tests\TestCase`):
  `actingAsAdmin()`, `actingAsUser()`, `actingAsUnverifiedUser()`,
  `assertApiSuccess()`, `assertGenericNotFound()`, `assertEmailNotVerified()`.
- Use `#[DataProvider]` for lists of invalid inputs. Cover the happy path,
  401 for guests, 422 for invalid input (including wrong types), 403 for a
  regular user on administrator-only actions, and 404 for unknown ids.
- Tests must not depend on the demo seed data.
- E2E rules (rate limits, CSP watcher, `test.fail()` policy) are in
  [tests/e2e/AGENTS.md](tests/e2e/AGENTS.md).

## Definition of done

All of these pass before you say a change is finished. Report the actual
output; never claim a check passed without running it.

1. `docker compose exec -T app composer lint`
2. `docker compose exec -T app composer analyse`
3. `docker compose exec -T app php artisan test`
4. `npm run lint`
5. `npm run format:check`
6. `npm run build`
7. If `docs/api/openapi.yaml` changed: the Redocly lint command reports no errors.
8. If pages or flows changed: the E2E tests pass (`tests/e2e/setup/*.sh`).
9. If dependencies changed: `composer audit` and `npm audit` report nothing.
10. Docs updated when behaviour, configuration or setup changed (README,
    ARCHITECTURE, SECURITY, docs/). Breaking changes get upgrade and
    rollback steps in [docs/upgrading.md](docs/upgrading.md).

## Git and pull requests

- Branch from `main` as `<type>/<short-topic>`. Commit only when asked.
- [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/), as
  in `git log`: `feat(products): ...`, `fix(dev): ...`, `test(e2e): ...`,
  `docs: ...`, `ci: ...`, `refactor: ...`, `chore(docker): ...`. Summary in
  the imperative mood; the body explains why. One logical change per commit.
- Follow the pull request checklist in [CONTRIBUTING.md](CONTRIBUTING.md).

## Do not

- Edit generated or vendored files: `public/build/`, `public/hot`,
  `vendor/`, `node_modules/`, `bootstrap/cache/`. Do not edit
  `composer.lock` or `package-lock.json` by hand; change them only with
  `composer` or `npm` commands.
- Weaken the CSP or other headers in `SecurityHeaders`, add `'unsafe-eval'`
  or `'unsafe-inline'` to `script-src`, or load anything from a CDN.
- Disable, skip or delete tests, lower the Larastan level, add a PHPStan
  baseline or `@phpstan-ignore`, or turn off lint rules to make a check pass.
- Remove the `verified` middleware or move an authenticated route out of
  the `auth:api` + `verified` group.
- Run destructive database commands on the development database
  (`migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, or
  `docker compose down -v`). Tests do not need them.
- Seed accounts, commit `.env` files or Passport keys, or print secrets.
- Change CI workflows, Docker files or deployment templates unless the
  task asks for it.
