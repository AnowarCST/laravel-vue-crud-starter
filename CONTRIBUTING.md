# Contributing

Thank you for helping to improve this project. This guide explains how to
set up a development environment, the conventions we follow, the checks a
pull request must pass, and how to add a new CRUD module.

By taking part you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting security issues

**Do not open a public issue, discussion or pull request for a security
problem.** Report it privately as described in [SECURITY.md](SECURITY.md)
(GitHub: **Security** tab, then **Report a vulnerability**).

## Before you start

- For a bug, open an issue with steps to reproduce, what you expected and
  what happened. Include versions (PHP, Node, browser) and whether you use
  Docker.
- For a new feature or a larger change, open an issue first so we can agree
  on the approach before you spend time on it.
- Read [ARCHITECTURE.md](ARCHITECTURE.md) for how the pieces fit together.

## Development setup

Follow [README.md](README.md): "Quick start with Docker (recommended)" or
"Installing without Docker". The commands below assume the Docker setup. If
you work without Docker, run the same `composer`, `php artisan` and `npm`
commands directly.

Useful to know:

- Tests always use an in-memory SQLite database (forced in `phpunit.xml`),
  so they never touch your MySQL data.
- Every email the app sends locally goes to Mailpit at
  <http://localhost:8025>.
- In the local environment the API documentation is at
  <http://localhost:8000/docs>.
- The optional demo catalogue (`docker compose exec app php artisan db:seed`)
  gives the products page and the dashboard something to show. Tests must
  not depend on it.

## Branches and commits

- Branch from `main` and name the branch `<type>/<short-topic>`, for example
  `feat/order-export`, `fix/product-upload-size` or `docs/api-examples`.
- Use [Conventional Commits](https://www.conventionalcommits.org/en/v1.0.0/)
  for commit messages, as in the existing history:

  ```text
  <type>(<optional scope>): <summary in the imperative mood>

  <body: what changed and why>
  ```

  Types in use: `feat`, `fix`, `refactor`, `docs`, `test` (changes that
  only touch tests), `ci` (workflows) and `chore`. Scopes in use include
  `backend`, `frontend`, `products`, `branding`, `docker`, `dev`, `api`,
  `e2e` and `security`. Examples from the history:
  `feat(frontend): migrate to Vite, Vue 3.5, Vue Router 5 and AdminLTE 4`,
  `fix(dev): allow the Laravel origin in the Vite dev server CORS settings`,
  `test(e2e): add a Playwright suite for the main user flows`.
- Keep each commit focused on one logical change. Use the body to explain why
  the change is needed, not only what it does.
- If a change breaks existing installations (configuration, database,
  behaviour), say so in the commit body and add upgrade and rollback steps to
  the README.
- Never commit secrets. `.env` is ignored by git; `.env.example` contains
  empty placeholders only.

## Quality checks

CI (`.github/workflows/ci.yml`) runs all of these on every pull request. Run
them locally before you push. Two more workflows run on pull requests: the
end-to-end tests (`.github/workflows/e2e.yml`, every pull request) and the
OWASP ZAP baseline scan (`.github/workflows/zap.yml`, pull requests to `main`
that change code or configuration that shapes HTTP responses). See the
README sections "End-to-end tests" and "Security scan (ZAP)".

| Check | Command | Fix automatically |
| --- | --- | --- |
| PHP code style (Pint) | `docker compose exec app composer lint` | `docker compose exec app composer format` |
| PHP static analysis (Larastan, level 9) | `docker compose exec app composer analyse` | |
| PHP tests (PHPUnit) | `docker compose exec app php artisan test` | |
| JavaScript and Vue lint (ESLint) | `docker compose --profile dev run --rm node npm run lint` | `npm run lint:fix` |
| JavaScript and Vue formatting (Prettier) | `docker compose --profile dev run --rm node npm run format:check` | `npm run format` |
| Production build (Vite) | `docker compose --profile dev run --rm node npm run build` | |
| PHP dependency audit | `docker compose exec app composer audit` | |
| npm dependency audit | `docker compose --profile dev run --rm node npm audit` | |

If you change [docs/api/openapi.yaml](docs/api/openapi.yaml), also lint it
with [Redocly CLI](https://redocly.com/docs/cli/). It must report no errors:

```bash
docker run --rm -e REDOCLY_TELEMETRY=off -v "$PWD/docs/api":/spec:ro -w /spec \
  redocly/cli:2.82.0 lint openapi.yaml
```

### End-to-end tests

The Playwright tests in `tests/e2e/` drive the real application in Chromium.
README.md ("End-to-end tests") explains how to run them. When you write or
change one:

- Make every test create the records it needs (through the API, with the
  `createCategory` and `createProduct` helpers in
  `tests/e2e/support/helpers.js`, or through the UI) and give them unique
  names with `uniqueSuffix()`. The stack loads the demo catalogue, and other
  tests add records too, so never assume what the database contains. To
  check a count, read it first and expect it to change by the number of
  records the test created.
- Reuse the saved sessions (`test.use({ storageState: storageState.user })`)
  instead of signing in through the form. Logins are limited to 10 per
  minute per IP address.
- The API allows 60 requests per minute per account. Use the `user` account
  when a regular user is allowed to do what the test does, and `admin2` to
  spread administrator requests.
- Every test fails on a Content-Security-Policy violation or an uncaught
  JavaScript error (`tests/e2e/support/test.js`). Fix the cause; do not
  remove the check.
- A known bug can be kept visible with `test.fail()` and a comment that
  explains it, as in `tests/e2e/profile.spec.js`. Remove the marker in the
  pull request that fixes the bug.

Do not lower the Larastan level, add a baseline, or disable lint rules to
make a check pass. Fix the code instead, or explain in the pull request why
an exception is needed.

## Adding a CRUD module

The existing modules (products, categories, tags and users) all follow the
same pattern. Use `ProductController`, `ProductRequest`, `ProductPolicy` and
`ProductsPage.vue` as the reference. The steps below use a hypothetical
`Widget` resource.

1. **Migration and model.** Add a migration in `database/migrations/` and a
   model in `app/Models/Widget.php`. List the request fields in `$fillable`.
   Never make a privilege-bearing column (such as `users.type`) mass
   assignable. Add a factory in `database/factories/` for the tests.

2. **Policy.** Create `app/Policies/WidgetPolicy.php`. Laravel finds it by
   name, so no registration is needed. Follow the existing rule unless you
   have a reason not to: any verified account may view, create and update,
   and only administrators may delete.

   ```php
   public function delete(User $user, Widget $widget): bool
   {
       return $user->isAdmin();
   }
   ```

3. **Form Request.** Create `app/Http/Requests/Widgets/WidgetRequest.php`.
   `authorize()` only checks that there is a user; the controller does the
   per-record policy check. Give every string a `max` length, and validate
   types strictly (for example `integer` and `exists:` for foreign keys).

4. **Resource and controller.** Create `app/Http/Resources/WidgetResource.php`
   listing every field the API returns, and
   `app/Http/Controllers/Api/V1/WidgetController.php` extending
   `App\Http\Controllers\Controller`:
   - return `ApiResponse::success(new WidgetResource($widget), 'Message')`
     (`App\Http\Responses\ApiResponse`) so every result has the
     `{success, data, message}` envelope;
   - paginate lists with
     `Widget::latest()->paginate(10)->through(fn (Widget $widget) => new WidgetResource($widget))`;
   - look records up with `findOrFail()` so missing ids return `404`;
   - call `$this->authorize('create', Widget::class)`,
     `$this->authorize('update', $widget)` and
     `$this->authorize('delete', $widget)` before changing anything;
   - only save validated input (`$request->validated()` or
     `$request->safe()->only([...])`).

5. **Route.** Register the resource in `routes/api.php` **inside** the
   `auth:api` + `verified` group, for example by adding
   `'widget' => WidgetController::class` to the `Route::apiResources([...])`
   list. `EmailVerificationTest` fails if an API route is outside that group.

6. **OpenAPI document.** Describe every new operation in
   [docs/api/openapi.yaml](docs/api/openapi.yaml): request body, the success
   response in the `{success, data, message}` envelope, and the error
   responses (reuse `#/components/responses/...`). `OpenApiDocumentTest`
   fails if a route is missing from the document. Lint it as shown above.

7. **Vue page.** Add `resources/js/components/WidgetsPage.vue`, following
   `components/product/CategoriesPage.vue`: the `Form` helper from
   `lib/Form.js` for create and edit (it shows `422` errors through the
   `<has-error>` component), `track()` from `lib/progress.js` around requests,
   `showModal()`/`hideModal()` from `bootstrap.js`, the helpers in
   `lib/notify.js` for confirmations and messages, and `<pagination-nav>` for
   paging. Add the route to `resources/js/routes.js` and a menu entry to
   `App.vue`. `meta.admin` and `$gate.isAdmin()` only hide UI; the API must
   enforce every rule on its own.

8. **Tests.** Add `tests/Feature/WidgetTest.php` with `RefreshDatabase` and
   `actingAs($user, 'api')`. Cover at least:
   - the happy path for create, read, update and delete;
   - `401` for guests;
   - `422` for invalid input, including wrong types (an array where a string
     is expected);
   - `403` when a regular user tries an administrator-only action;
   - `404` for an unknown id.

   `EmailVerificationTest` already checks that unverified accounts get `403`
   on every API route.

9. **End-to-end test.** Add `tests/e2e/widgets.spec.js` that creates, edits
   and deletes a widget through the page, following
   `tests/e2e/catalogue.spec.js`. If the page is new, also add its path to
   the list in `tests/e2e/security.spec.js`.

## Pull request checklist

- [ ] The pull request explains what changed and why, and links the issue.
- [ ] Commits follow Conventional Commits and are focused.
- [ ] `composer lint`, `composer analyse` and `php artisan test` pass.
- [ ] `npm run lint`, `npm run format:check` and `npm run build` pass.
- [ ] `composer audit` and `npm audit` report no vulnerabilities, or the
      pull request explains why one cannot be fixed yet.
- [ ] New or changed behaviour has tests, including authorization and
      validation failures.
- [ ] Changes to pages are covered by the end-to-end tests, and the E2E
      workflow passes.
- [ ] New or changed `/api` routes are documented in
      `docs/api/openapi.yaml`, and Redocly CLI reports no errors.
- [ ] No inline scripts, inline event handlers or third-party CDNs were
      added; pages still work under the Content-Security-Policy.
- [ ] No secrets, personal data or real credentials in code, tests, fixtures,
      screenshots or commit messages.
- [ ] Breaking changes have upgrade and rollback steps in the README.
- [ ] Documentation (README, ARCHITECTURE, SECURITY) is updated if
      behaviour, configuration or setup changed.

## License

By contributing, you agree that your contributions are licensed under the
[MIT License](LICENSE).
