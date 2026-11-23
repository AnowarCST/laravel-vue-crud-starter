# Testing and code quality

How to run the PHP tests, the code quality checks, the end-to-end tests and
the OWASP ZAP scan, locally and in CI. Rules for writing tests are in
[CONTRIBUTING.md](../CONTRIBUTING.md); when each CI workflow runs is in
[docs/ci-cd.md](ci-cd.md).

## PHP tests

```bash
docker compose exec app php artisan test
# or a single suite, file or test class
docker compose exec app php artisan test --testsuite=Feature
docker compose exec app php artisan test tests/Feature/Api/ResponseFieldsTest.php
docker compose exec app php artisan test --filter=ProductListTest
```

Tests always use an in-memory SQLite database (forced in `phpunit.xml`) and
never touch your MySQL data. Feature tests are grouped by area in
`tests/Feature/` (`Api/`, `Auth/`, `Security/`, `Docs/`, `Database/`,
`Web/`); shared helpers are in `tests/Concerns/`.

## Code quality checks

| Check | Command | Fix automatically |
| --- | --- | --- |
| PHP code style (Pint) | `docker compose exec app composer lint` | `docker compose exec app composer format` |
| PHP static analysis (Larastan, level 9) | `docker compose exec app composer analyse` | |
| PHP tests (PHPUnit) | `docker compose exec app php artisan test` | |
| JavaScript and Vue lint (ESLint) | `docker compose --profile dev run --rm node npm run lint` | `npm run lint:fix` |
| JavaScript and Vue formatting (Prettier) | `docker compose --profile dev run --rm node npm run format:check` | `npm run format` |
| Production build (Vite) | `docker compose --profile dev run --rm node npm run build` | |
| Dependency audits | `docker compose exec app composer audit` and `docker compose --profile dev run --rm node npm audit` | |

With Node 26 installed on your machine you can run the `npm` commands
directly (`npm run lint`). Without Docker, run the same `composer` and
`php artisan` commands directly.

In CI, `.github/workflows/ci.yml` runs all of these on every pull request
and every push to `main`. The production Docker build
(`.github/workflows/docker.yml`), the end-to-end tests and the ZAP scan are
opt-in: they run when a maintainer adds the `run-docker-build`, `run-e2e` or
`run-zap` label to a pull request, when someone starts them by hand from the
**Actions** tab, or on every pull request when the repository variable
`RUN_DOCKER_BUILD`, `RUN_E2E` or `RUN_ZAP` is `true`. See
[docs/ci-cd.md](ci-cd.md).

### OpenAPI document

If you change [docs/api/openapi.yaml](api/openapi.yaml), lint it with
[Redocly CLI](https://redocly.com/docs/cli/). It must report no errors:

```bash
docker run --rm -e REDOCLY_TELEMETRY=off -v "$PWD/docs/api":/spec:ro -w /spec \
  redocly/cli:2.82.0 lint openapi.yaml
```

`php artisan test` also runs `OpenApiDocumentTest`, which fails if an `/api`
route is missing from the document or the document lists an operation that
has no route.

## End-to-end tests

The Playwright tests in `tests/e2e/` use Chromium to sign in, manage users,
products, categories and tags, upload a product photo, search and filter the
product list, check the dashboard counts, register and verify a new account
(using the email caught by Mailpit), manage personal access tokens and open
the API documentation. Every test also fails if a page reports a
Content-Security-Policy violation or an uncaught JavaScript error.

They run against a throwaway stack: the production Docker image, MySQL (data
kept in memory only) and Mailpit. It does not touch your development
database. Each run generates new random secrets and test accounts and stores
them in `tests/e2e/.stack/` (ignored by git). The stack loads the demo
catalogue, but the tests create their own records and do not depend on it.

Requirements: Docker with Docker Compose v2, Node 26, and the project's npm
packages (`npm ci`). The stack publishes the app on `127.0.0.1:8080` and
Mailpit on `127.0.0.1:8125`, so those ports must be free. The first run
downloads the Playwright image (`mcr.microsoft.com/playwright:v1.65.0-noble`)
and builds the production image, which needs several GB of free Docker disk
space.

```bash
npm ci
tests/e2e/setup/start-stack.sh                       # build the prod image, start the stack, migrate, seed, create test accounts
tests/e2e/setup/run-tests.sh                         # run every test in the Playwright image
tests/e2e/setup/run-tests.sh tests/e2e/auth.spec.js  # or only some files (arguments go to `playwright test`)
tests/e2e/setup/stop-stack.sh                        # remove the containers, volumes and generated secrets
```

`start-stack.sh` reads these optional environment variables: `E2E_PROJECT`
(Compose project name, default `lvcs-e2e`), `E2E_APP_PORT` (default `8080`),
`E2E_MAILPIT_PORT` (default `8125`), `E2E_IMAGE` (default `lvcs-e2e:local`)
and `E2E_SKIP_BUILD=1` (use an image you already built). `run-tests.sh` only
works with `E2E_APP_PORT=8080`. With another port, run Playwright on your
machine instead: `npx playwright install chromium`, then `npm run test:e2e`
(or `npm run test:e2e:ui` for the interactive mode).

The HTML report is written to `tests/e2e/playwright-report/` and failure
traces to `tests/e2e/test-results/`. Both can contain the test accounts'
passwords for that run, so do not share them outside your team. The stack
sets `API_DOCS_ENABLED=true` so the `/docs` tests can run; because it uses
`APP_ENV=production`, only administrators can open `/docs` there.

Two limits matter if you add tests. The app allows 10 login attempts per
minute per IP address, so wait a minute before starting a second full run.
The API allows 60 requests per minute per account, so the tests reuse saved
sessions and spread their requests over two administrator accounts and one
regular user. One test is marked with `test.fail()` on purpose: it shows a
known bug (changing your own password also signs out the current session)
and will start failing, as an unexpected pass, once the bug is fixed.

In CI, `.github/workflows/e2e.yml` runs the same scripts and uploads the
report as the `playwright-report` artifact. The workflow is opt-in: add the
`run-e2e` label to a pull request, start **E2E** by hand from the
**Actions** tab, or set the repository variable `RUN_E2E` to `true` to run
it on every pull request and push to `main`.

## Security scan (ZAP)

`.github/workflows/zap.yml` runs an [OWASP ZAP](https://www.zaproxy.org/)
baseline scan against the same throwaway stack. ZAP crawls the signed-out
pages and checks the responses passively; it does not attack the
application.

The scan is opt-in. It runs when started by hand from the **Actions** tab
(**ZAP baseline**, then **Run workflow**), on a pull request to `main` that
has the `run-zap` label, and, when the repository variable `RUN_ZAP` is
`true`, on every pull request to `main` and every Monday. Add the label to
pull requests that change HTTP headers, cookies, the
Content-Security-Policy or the Apache configuration.

`.zap/rules.tsv` sets what each finding does. Rules set to `FAIL` cover
protections the app has today, such as the clickjacking and
`X-Content-Type-Options` headers, the Content-Security-Policy, SameSite
cookies and CSRF tokens on forms, so a match means a regression and fails
the job. Rules set to `IGNORE` are false positives that were checked by hand;
the file explains each one. All other findings are listed in the job log and
in the `zap-baseline-report` artifact without failing the job.
