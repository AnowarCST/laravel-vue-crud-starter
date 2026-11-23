@AGENTS.md

## Claude Code

The shared instructions above (imported from `AGENTS.md`) apply in full.
This section only adds what is specific to Claude Code. Change shared rules
in `AGENTS.md`, not here (see `docs/ai-assistants.md`).

- **Run PHP in Docker.** The host has no PHP toolchain. Use
  `docker compose exec -T app ...` for `composer`, `php artisan` and the
  tests. Check that the stack is up with `docker compose ps`; if `app` is
  not running, ask before starting or rebuilding containers.
- **Use the documented commands.** Prefer the exact commands in the
  "Commands" and "Definition of done" sections. `.claude/settings.json`
  pre-approves only the fixed, argument-free quality checks (for example
  `docker compose exec -T app php artisan test`). Any variant, such as a
  filtered test run (`php artisan test --filter ...`) or a single test
  file, needs normal approval; do not try to work around that. `/check`
  runs the whole definition-of-done list.
- **Verify before you claim.** Run the relevant checks after every change
  and quote the result (for example the test count PHPUnit prints). If a
  check cannot run, say so and why, instead of assuming it passes.
- **Look first, then change.** Before editing, read the reference
  implementation named in `AGENTS.md` (`ProductController`,
  `ProductRequest`, `CategoriesPage.vue`), the matching tests, and the
  scoped `AGENTS.md` for the folder. `ARCHITECTURE.md` explains how the
  pieces connect.
- **Scoped instructions.** `resources/js/`, `tests/e2e/` and `database/`
  each have an `AGENTS.md`. Their `CLAUDE.md` imports it, so Claude Code
  loads it when you work on files there.
- **Ask before** anything that touches shared state: `git push`, creating
  pull requests, changing CI workflows, Docker files or deployment
  templates, deleting Docker volumes, or running migrations and seeders on
  the development database.
- **Interactive commands** such as `php artisan app:create-admin` prompt
  for a password. Ask the user to run them; never pass a password on the
  command line.
