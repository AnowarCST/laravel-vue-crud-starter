# Modernisation task tracker

Status of the 2026 security hardening and upgrade. "Done" means implemented,
covered by tests or checks, and passing in CI.

| ID | Milestone | Status | Notes |
| --- | --- | --- | --- |
| 1 | Framework upgrade | Done | PHP 8.5, Laravel 13, Passport 13, streamlined `bootstrap/app.php` structure. |
| 2 | Frontend migration | Done | Laravel Mix replaced by Vite. Vue 3, Vue Router, AdminLTE 4, Bootstrap 5. |
| 3 | Authentication | Done | Passport stays as the API guard. Registration creates regular users only, email verification is required, and admins are created with `php artisan app:create-admin`. |
| 4 | API hardening | Done | Form Requests, policies, rate limits, generic error messages, safe image uploads with server-side re-encoding. |
| 5 | Security hardening | Done | Nonce-based CSP and security headers, strict CORS, hardened sessions, `SECURITY.md`. |
| 6 | Docker and developer experience | Done | Multi-stage image (dev and prod), MySQL 9.7 LTS, Mailpit, `/up` health checks, localhost-only ports. |
| 7 | Code quality and CI | Done | Pint, Larastan level 9, ESLint, Prettier, PHPUnit, `composer audit`, `npm audit`, CodeQL and Dependabot in CI. |
| 8 | End-to-end tests (Playwright) | Planned | Tracked in a separate pull request. |
| 9 | Contributor and API documentation | Planned | `CONTRIBUTING.md`, `ARCHITECTURE.md`, OpenAPI documentation. Tracked in a separate pull request. |
| 10 | Dynamic security scan (OWASP ZAP) | Planned | Tracked in a separate pull request. |

## Known follow-ups

- The `roles`, `role_user` and `social_accounts` tables are no longer used by
  any code. They are kept because existing installations have already created
  them. A later release can drop them with a new migration and a release note.
- Passport's JSON API (used by the Developer page) is deprecated in Passport
  13. It still works, but a future Passport release may remove it.
