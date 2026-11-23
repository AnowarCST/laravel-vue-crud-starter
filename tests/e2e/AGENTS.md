# AGENTS.md: end-to-end tests (`tests/e2e/`)

Applies in addition to the [root AGENTS.md](../../AGENTS.md). How to run
the tests and what the stack contains:
[docs/testing.md](../../docs/testing.md#end-to-end-tests). Writing rules in
more detail:
[CONTRIBUTING.md](../../CONTRIBUTING.md) ("End-to-end tests").

## Running

```bash
npm ci                                               # once; the runner uses this checkout's node_modules
tests/e2e/setup/start-stack.sh                       # prod image + MySQL + Mailpit, test accounts
tests/e2e/setup/run-tests.sh                         # whole suite, in the Playwright Docker image
tests/e2e/setup/run-tests.sh tests/e2e/auth.spec.js  # only some files (arguments go to `playwright test`)
tests/e2e/setup/stop-stack.sh                        # remove containers, volumes and generated secrets
```

- The stack is throwaway and separate from the development stack. It
  publishes the app on `127.0.0.1:8080` and Mailpit on `127.0.0.1:8125`;
  `run-tests.sh` needs port 8080.
- `start-stack.sh` writes random secrets and account passwords to
  `tests/e2e/.stack/` (ignored by git). Never commit or print them. The
  HTML report (`tests/e2e/playwright-report/`) and traces
  (`tests/e2e/test-results/`) can contain them too.
- Wait a minute between two full runs against the same stack (login rate
  limit, see below).

## Writing tests

- Import `test` and `expect` from `./support/test.js`, not from
  `@playwright/test`. Its auto fixture fails every test on a
  Content-Security-Policy violation or an uncaught page error. Fix the
  cause; never remove or weaken the check. A context the test creates
  itself must be passed to `browserIssues.watch()`.
- Reuse the saved sessions: `test.use({ storageState: storageState.admin })`
  (`admin`, `admin2` or `user`, from `./support/env.js`). `auth.setup.js`
  creates them. Sign in through the form (`login()` in
  `./support/helpers.js`) only when the test is about signing in.
- Rate limits apply to the test run: 10 login attempts per minute per IP and
  60 API requests per minute per account. Use `user` when a regular user may
  do what the test does, and `admin2` to spread administrator requests.
- Never depend on what the database contains. The stack loads the demo
  catalogue and other tests add records. Create what you need with
  `createCategory()` / `createProduct()` or through the UI, name it with
  `uniqueSuffix()`, and check counts as "changed by N" rather than exact
  values.
- Prefer role and label locators (`getByRole`, `getByLabel`) and the helpers
  `rowWith()`, `expectToast()`, `confirmDeletion()`, `waitForEmail()`.
- Tests run one at a time (`workers: 1` in `playwright.config.js`). Do not
  turn on parallel runs.
- A new dashboard page also goes into the `appPages` list in
  `security.spec.js`.

## Known bugs and `test.fail()`

A known bug may be kept visible with `test.fail(true, 'Known bug: ...')`
and a comment that explains it, as in `profile.spec.js`. Playwright then
reports an unexpected pass once the bug is fixed; remove the marker in the
pull request that fixes it. Never use `test.fail()`, `test.skip()` or
`test.fixme()` to hide a failure you introduced, and never commit
`test.only()` (CI sets `forbidOnly`).
