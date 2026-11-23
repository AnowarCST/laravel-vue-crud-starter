# AI coding assistants

This repository includes instructions for AI coding assistants. They follow
one rule: **[AGENTS.md](../AGENTS.md) is the single source of truth.** Every
other instruction file either imports it or points to it and adds only what
is specific to one tool or one folder. Copies of the same rule in several
files drift apart, so a rule lives in one place only.

The instructions are written for people too: `AGENTS.md` is a short summary
of how this project is built, tested and reviewed, with links to the longer
documents ([ARCHITECTURE.md](../ARCHITECTURE.md),
[CONTRIBUTING.md](../CONTRIBUTING.md), [SECURITY.md](../SECURITY.md),
[docs/ci-cd.md](ci-cd.md) and [docs/api/openapi.yaml](api/openapi.yaml)).

## Files

| File | Read by | Contents |
| --- | --- | --- |
| [`AGENTS.md`](../AGENTS.md) | OpenAI Codex, Cursor, Claude Code (through `CLAUDE.md`) and other tools that support the `AGENTS.md` convention | Stack, commands, directory map, conventions, API contract, security and testing rules, definition of done, git conventions, "do not" list |
| [`resources/js/AGENTS.md`](../resources/js/AGENTS.md) | Same | Vue, AdminLTE, API calls and errors, Content-Security-Policy |
| [`tests/e2e/AGENTS.md`](../tests/e2e/AGENTS.md) | Same | Playwright stack scripts, rate limits, CSP watcher, `test.fail()` policy |
| [`database/AGENTS.md`](../database/AGENTS.md) | Same | Migrations, factories, seeders |
| [`CLAUDE.md`](../CLAUDE.md) | Claude Code | `@AGENTS.md` import plus Claude Code specific guidance |
| `resources/js/CLAUDE.md`, `tests/e2e/CLAUDE.md`, `database/CLAUDE.md` | Claude Code | One line, `@AGENTS.md`, which imports the folder's `AGENTS.md` |
| [`.claude/settings.json`](../.claude/settings.json) | Claude Code | Allows a fixed list of quality checks, exactly as written and without extra arguments, without a prompt; denies reading `.env` files, Passport keys and the E2E stack secrets with Claude Code's file tools |
| [`.claude/commands/check.md`](../.claude/commands/check.md) | Claude Code | The `/check` command, which runs the definition-of-done checks and reports the results |
| [`.cursor/rules/*.mdc`](../.cursor/rules) | Cursor | `project.mdc` (always applied) points to `AGENTS.md`; `laravel-backend.mdc`, `vue-frontend.mdc` and `e2e-tests.mdc` are attached for matching files; `security.mdc` is applied when the change is about security |

How each tool finds its files:

- **OpenAI Codex** reads `AGENTS.md` files from the repository root down to
  the directory it was started in, and joins them; files closer to that
  directory come later and win. It does not load a folder's `AGENTS.md`
  because a file in that folder is edited, so the root `AGENTS.md` lists the
  scoped files and asks the agent to read them.
- **Claude Code** reads `CLAUDE.md`. When a `CLAUDE.md` exists it does not
  read `AGENTS.md` on its own (by default), so `CLAUDE.md` imports it with
  `@AGENTS.md`. The `CLAUDE.md` files in subfolders load when Claude Code
  works on a file in that folder, and import the folder's `AGENTS.md` the
  same way.
- **Cursor** reads `AGENTS.md` in the root and in subfolders, and the
  project rules in `.cursor/rules/`. Rules use the `.mdc` format with
  `description`, `globs` and `alwaysApply` front matter, and reference
  files with `@path` instead of copying them.

The Claude Code allowlist only covers fixed commands. It contains no
wildcards on purpose: a pattern such as `php artisan test *` would also
approve options like `--bootstrap=<file>`, which run arbitrary code. Other
commands, including filtered test runs, ask for approval as usual. The
`Read` deny rules only stop Claude Code from opening those files itself;
they do not stop code that runs inside the containers (tests, Artisan
commands, scripts) from reading secrets such as `.env`. Review what an
assistant asks to run.

Personal, uncommitted settings belong in `.claude/settings.local.json` or
your editor's user settings. `.claude/worktrees/` and
`.claude/settings.local.json` are ignored by git.

## Keeping the files in sync

When a convention, command or rule changes:

1. Change it in `AGENTS.md` (or in the scoped `AGENTS.md` if it only
   applies to that folder), in the same pull request as the code change.
2. Check whether `CLAUDE.md`, `.cursor/rules/*.mdc` or
   `.claude/commands/check.md` mention it. They should only point to
   `AGENTS.md` or give a one-line reminder; update or remove the reminder
   rather than adding detail there.
3. If a command changed, update `.claude/settings.json` so the allowed
   commands still match the documented ones. Add only exact commands,
   never a pattern with `*` or `:*`.
4. Run every command you wrote down, and check that every path exists.
   Instructions that name a missing file or a failing command mislead the
   assistant. `npm run format:check` also checks the Markdown files in
   `resources/js/`, so run `npm run format` after editing
   `resources/js/AGENTS.md`.
5. Keep `AGENTS.md` short (about 200 lines). Move detail into the longer
   documents and link to it.

The longer documents (README, ARCHITECTURE, CONTRIBUTING, SECURITY,
`docs/`) stay the reference for people. When they and `AGENTS.md`
disagree, fix whichever is wrong in the same pull request.

## Using an assistant on this project

- Treat suggestions like any other contribution: they go through the same
  review, checks and pull request checklist in
  [CONTRIBUTING.md](../CONTRIBUTING.md).
- Do not paste secrets, `.env` contents, tokens or personal data into a
  prompt.
- Assistants must not push, open pull requests or change CI, Docker or
  deployment files unless you ask them to.
