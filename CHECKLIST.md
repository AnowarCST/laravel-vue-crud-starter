# Contributor Checklist

Before you open a Pull Request (PR), please run through this list. Tick every item or explain why it does not apply.

## Build, Lint, Test
- [ ] PHP builds locally; framework boots.
- [ ] `composer test` (Pest/PHPUnit) passes.
- [ ] Front-end builds with Vite: `npm run build` (or `pnpm build`) passes.
- [ ] Static analysis clean: `vendor/bin/phpstan analyse` (Larastan) and `php artisan pint` (or `composer pint`) run.
- [ ] ESLint/Prettier clean for Vue/TS files.

## Security & Compliance
- [ ] No secrets committed; `.env` changes documented only in `.env.example`.
- [ ] Input validation via FormRequests; outputs escaped in Blade/Vue.
- [ ] CORS/CSP headers unaffected or improved by this change.
- [ ] Dependencies audited: `composer audit` and `npm audit` (or `pnpm audit`) reviewed.
- [ ] If AI-generated code was used, it has been **reviewed by a human** and sources are attributed where relevant.

## Docs & DX
- [ ] README updated when commands or flows change.
- [ ] API changes reflected in `openapi.yaml` (or API docs) and sample requests.
- [ ] Docker (or Sail) workflow tested: `docker compose up` runs cleanly.
- [ ] Added/updated seeders/factories for new data models.

## Scope & PR Hygiene
- [ ] One logical change per PR; squashed commits where appropriate.
- [ ] Clear title & description; linked issues (e.g., `Closes #123`). 
- [ ] Screenshots/GIFs included for UI changes.
- [ ] Backwards compatibility considered or migration notes provided.
