# Project Rules (Open Source & AI-Friendly)

This repository aims to be beginner-friendly, secure, and productive for humans and AI-assisted contributors. These rules keep quality high and reviews fast.

## 1) Coding Standards
- **Backend:** Laravel 11+, PHP 8.3+, PSR-12 via **Laravel Pint**; strict types where possible; validation with FormRequests; authorisation via Policies/Gates.
- **Frontend:** Vue 3 + Vite, Vue Router 4, Pinia; ESLint + Prettier; Composition API; single-file components kept small.
- **Testing:** Pest/PHPUnit for PHP; Playwright/Vitest optional for UI; aim for meaningful tests over raw percentages.

## 2) Security First
- Never commit secrets; use `.env` and update `.env.example` only.
- Validate/authorise every write; escape outputs in Blade/Vue.
- Prefer **Sanctum** for SPA auth (Passport optional/preset).
- Set sensible rate limits; avoid leaking stack traces in prod.
- Keep headers sane (CSP/CORS); see `SECURITY.md` if present.

## 3) AI-Friendly Practices
- Keep functions small and composable; write crisp docblocks.
- Keep **OpenAPI** (`openapi.yaml`) in sync with routes/resources.
- Add short **CONTEXT.md** notes when patterns are non-obvious.
- When using AI code: credit inspirations, **review manually**, and ensure licences are compatible.

## 4) Git Hygiene
- Branches: `feat/*`, `fix/*`, `chore/*`, `docs/*`, `refactor/*`.
- Conventional commit messages (e.g., `feat(api): add product filter`). 
- Rebase or squash before merge to keep history tidy.

## 5) Pull Requests
- One focused change per PR; include tests & docs.
- Link issues; describe migration/rollback steps if schema changes.
- Provide screenshots/GIFs for UI changes; note a11y impact.

## 6) Documentation
- Update **README** for commands, env vars, and setup.
- Add/extend **ARCHITECTURE.md** when structure changes.
- Keep **API.md** or the **OpenAPI** spec current.

## 7) Packages & Versions
- Prefer stable releases; pin major versions in `composer.json` and `package.json`.
- Justify heavy dependencies; remove unused packages promptly.
- Use Dependabot or similar to track updates.

## 8) Community & Conduct
- Be respectful and patient; assume good intent.
- Review PRs with empathy and concrete suggestions.
- Follow the `CODE_OF_CONDUCT.md`.

## 9) Docker & DX
- Ensure `docker compose up` (or Sail) works out of the box.
- Multi-stage Dockerfile for prod; non-root user; healthchecks.
- Provide a quick `make up`, `make test`, `make lint` if Makefile exists.

## 10) What Maintainers Look For
- Clear problem statement; minimal diff; passing checks.
- Security awareness; good names; comments only where needed.
- Tests that fail first, then pass with the fix/feature.

_If in doubt: open a draft PR early and ask for guidance._
