---
description: Run the definition-of-done checks from AGENTS.md and report the results
allowed-tools: Bash(docker compose ps) Bash(docker compose exec -T app composer lint) Bash(docker compose exec -T app composer analyse) Bash(docker compose exec -T app php artisan test) Bash(npm run lint) Bash(npm run format:check) Bash(npm run build) Bash(git status --short) Bash(git diff --stat)
disable-model-invocation: true
---

Run the "Definition of done" checks from `AGENTS.md` for the current working
tree. Do not change any files while doing this.

1. Run `docker compose ps`. If the `app` service is not running, stop and
   tell me; do not start or rebuild containers.
2. Run each command exactly as written (no extra arguments), one at a
   time, and keep going after a failure:
   - `docker compose exec -T app composer lint`
   - `docker compose exec -T app composer analyse`
   - `docker compose exec -T app php artisan test`
   - `npm run lint`
   - `npm run format:check`
   - `npm run build`
3. Look at `git status --short` and `git diff --stat`. Tell me which of the
   conditional checks in `AGENTS.md` apply (OpenAPI lint, E2E tests,
   dependency audits, documentation), but do not run them unless I ask.
4. Report a table with one row per check: command, passed or failed, and
   the key line of output (for example the PHPUnit test count). For each
   failure, quote the first error and name the file. Do not claim a check
   passed unless you ran it in this session.
