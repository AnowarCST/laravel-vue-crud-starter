# Modernisation Task Tracker

Branch: `security/hardening-and-upgrade-2026`. Status reflects the working tree
as of 2026-11-23; "Done" means implemented and verified locally, "Needs
integration check" means implemented but the final combined verification on
the integrated branch is still outstanding.

| ID | Milestone | Status | Notes |
| --- | --- | --- | --- |
| 0 | Prep & audit | Done | Pre-upgrade snapshots in `reports/` (local only). Open Dependabot PRs inventoried in `SECURITY_REFACTOR_PLAN.md`. |
| 1 | Framework upgrade (PHP 8.5, Laravel 13, Passport 13) | Needs integration check | composer.json/lock upgraded. On a working-tree snapshot, `composer validate --strict`, `composer audit` (no advisories) and `php artisan test` (61 passed) ran green in the PHP 8.5.13 dev image. |
| 2 | Frontend migration (Vue 3 + Vite + AdminLTE 4) | Needs integration check | Mix removed and Vite added (`vite.config.js`, output `public/build`). On a snapshot, `npm ci`, `npm audit` (0 vulnerabilities) and `npm run build` succeeded on Node 26.13.1. UI walkthrough of all CRUD screens still to do. |
| 3 | Auth model | Done (scope changed) | Passport 13 retained as the API guard; Sanctum switch dropped. Registration creates non-admins only; admins come from `php artisan app:create-admin`; no seeded admin. |
| 4 | API hardening | Done | Form Requests, admin-only account management, validated uploads, rate limiters, generic auth errors (covered by feature/security tests). OpenAPI docs: not planned for this release. |
| 5 | Security hardening | Done | Nonce CSP + security headers middleware, strict CORS, upload lockdown, `SECURITY.md`, hardened `.env.example`. |
| 6 | Docker & DX refresh | Needs integration check | Multi-stage `docker/Dockerfile` (dev/node-dev/prod), Apache as www-data on 8080, `/up` healthchecks, loopback-only ports, MySQL 8.4.13 not published. Verified: dev/node-dev/prod images build, prod image serves `/up` and is healthy, `docker compose config`, MySQL healthcheck. Full `docker compose up` with the bind mount not yet run. |
| 7 | Tests & CI | Done (E2E pending) | GitHub Actions CI (PHP, frontend, Docker prod build + smoke test), CodeQL, Dependabot. Azure pipeline repaired (PHP 8.5 via Docker). Pest conversion dropped; Playwright E2E pending (see below). |
| 8 | OSS hygiene & docs | In progress | README quickstart + Laravel 8 upgrade guide, `SECURITY.md`, `CONTEXT.md` updated. `CONTRIBUTING.md` / `ARCHITECTURE.md` not written. |

Legend: Done · Needs integration check · In progress · Pending · Dropped

## Before merging
- [ ] Integrated branch: clean `composer install` and `npm ci`, `php artisan test`, `npm run build`, `composer audit`, `npm audit`.
- [ ] `docker build --target prod` on the final lockfiles and `/up` smoke test (CI `docker` job does this).
- [ ] `docker compose up -d` from a fresh clone following README, then `migrate`, `passport:keys`, `storage:link`, `app:create-admin`, log in, and exercise CRUD + image upload.
- [ ] HTTP checks: `/.env`, `/storage/uploads/*.php` and `.html` are denied; uploaded images served with `nosniff`.
- [ ] Map the open Dependabot PRs to the new lockfiles and close the superseded ones (coordinator).
- [ ] Azure check passes on the PR (or the Azure integration is retired).

## E2E test plan (Playwright) - pending
- [ ] Add Playwright with config and npm scripts (`test:e2e`).
- [ ] Create the E2E admin in a test-only setup step (no seeded default admin).
- [ ] Specs: auth, users, categories, tags, products (upload), profile, password, errors.
- [ ] CI: add an E2E workflow that uploads the HTML report.
