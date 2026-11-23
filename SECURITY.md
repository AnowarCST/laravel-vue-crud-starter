# Security policy

## Supported versions

Only the latest commit on `main` receives security fixes. This project is a
starter template: once you copy or fork it, keeping your copy up to date is
your responsibility.

| Version | Supported |
| --- | --- |
| `main` (PHP 8.5, Laravel 13, Passport 13, Vue 3, Vite) | Yes |
| Releases built on Laravel 8, Vue 2 or Laravel Mix | No. See [Upgrading from the Laravel 8 version](README.md#upgrading-from-the-laravel-8-version) |

### Minimum safe dependency versions

These are the lowest versions that contain fixes for recent critical or
high-severity advisories that affect this stack. `main` is above all of them.
If you maintain a fork, do not go below these versions.

| Component | Minimum version | Advisories fixed |
| --- | --- | --- |
| PHP | 8.5.12 (or 8.4.27, 8.3.36, 8.2.35) | CVE-2026-109618, CVE-2026-109619 ("PHP2Shell", unauthenticated remote code execution) |
| `laravel/framework` | 13.29.0 | CVE-2026-114509 (authentication bypass), CVE-2026-114508, CVE-2026-48019 |
| `laravel/passport` | 13.7.2 | CVE-2026-39976 (client credentials token treated as a user) |

PHP 8.1 and older are end of life and have no official fix for PHP2Shell.

## Reporting a vulnerability

Please **do not open a public issue, discussion or pull request** for a
security problem.

Report it privately through GitHub instead:
[**Report a vulnerability**](https://github.com/AnowarCST/laravel-vue-crud-starter/security/advisories/new)
(the **Security** tab, then **Report a vulnerability**).

Please include:

- the commit or version you tested,
- the steps to reproduce the problem, or a proof of concept,
- what an attacker could do with it.

What happens next:

1. We acknowledge your report within 7 days.
2. We confirm the problem and agree on its severity with you.
3. We prepare a fix in a private security advisory. You are welcome to review
   it.
4. We publish the fix and the advisory at the same time and credit you, unless
   you prefer to stay anonymous.

### Scope

In scope: the code in this repository, its default configuration, the Docker
images built from `docker/Dockerfile`, and the GitHub Actions workflows.

Out of scope: vulnerabilities in Laravel, Passport, Vue, PHP or other
dependencies (report those to the project concerned), problems that need an
already compromised server or administrator account, and findings from
automated scanners without a demonstrated impact.

## How the application protects itself

**Accounts**

- There is no default administrator. Create one interactively with
  `php artisan app:create-admin`. The password is typed at a prompt and is
  never accepted as a command-line argument.
- Self-registration always creates a regular user. A role sent in the request
  is ignored.
- Self-registered users must verify their email address before they can use
  the dashboard or the API. Changing the email address requires verifying the
  new one. Accounts created by an administrator are verified automatically.
- Passwords must be at least 12 characters (and at most 72 bytes, the bcrypt
  limit).
- Changing a password revokes the user's OAuth access and refresh tokens and
  signs out their other browser sessions.

**Authorization**

- Only administrators can list, create, update or delete accounts.
  Administrators cannot change their own role or delete their own account.
- Only administrators can delete products, categories and tags.

**Uploads**

- Only JPEG, PNG and WebP images are accepted (5 MB and 4096 x 4096 pixels at
  most). Type, extension and content are checked on the server.
- Every image is decoded and re-encoded on the server. This removes metadata
  such as EXIF and GPS data, and anything hidden inside or appended to the
  file.
- Files get random names and are stored on the `public` disk
  (`storage/app/public/uploads`). The web server never executes files there
  and sends them with `X-Content-Type-Options: nosniff`.

**HTTP**

- Every response carries a Content-Security-Policy with a per-request nonce,
  plus `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy` and `Cross-Origin-Opener-Policy`. HSTS is added on
  HTTPS requests.
- CORS allows only `APP_URL` and the origins listed in `CORS_ALLOWED_ORIGINS`.
- Rate limits apply to login (10 per minute per IP, and a lockout after 5
  failures per email and IP), registration (5 per minute per IP), password
  reset (5 per minute per IP and 10 per hour per email) and the API (60 per
  minute per user or IP).

**Secrets**

- `.env` is never committed or copied into Docker images. `.env.example`
  contains empty placeholders only.
- `APP_KEY` encrypts sessions and cookies. To rotate it, move the old key to
  `APP_PREVIOUS_KEYS`, set a new `APP_KEY` and deploy. Remove the old key once
  every user has signed in again.
- Passport keys (`storage/oauth-*.key`, ignored by git) sign access tokens.
  In production, provide them through `PASSPORT_PRIVATE_KEY` and
  `PASSPORT_PUBLIC_KEY` or a mounted secret. Rotating them
  (`php artisan passport:keys --force`) signs everyone out of the API.

**Containers**

- Base images are pinned to exact versions and updated by Dependabot.
- The production image has no Composer, Node.js or git. Application code is
  owned by root and read-only. The web server runs as `www-data` and can only
  write to `storage/` and `bootstrap/cache/`.
- `docker-compose.yml` publishes ports on `127.0.0.1` only, does not publish
  MySQL, drops Linux capabilities and sets `no-new-privileges`.
- PHP runs with `expose_php`, `display_errors` and session upload progress
  turned off.

## Dependency and code checks

Every pull request runs:

- `composer audit` and `npm audit`,
- Pint, Larastan (level 9), ESLint and Prettier,
- the PHPUnit test suite,
- a production build of the frontend and of the Docker image,
- CodeQL analysis of the JavaScript code and the GitHub Actions workflows.

Dependabot proposes weekly updates for Composer, npm, Docker images and GitHub
Actions. Third-party GitHub Actions are pinned to full commit SHAs.

## Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning` or higher.
- [ ] A unique `APP_KEY`, strong database passwords, and Passport keys
      provided as secrets.
- [ ] HTTPS in front of the application, `APP_URL` starting with `https://`,
      and `SESSION_SECURE_COOKIE=true`.
- [ ] `TRUSTED_PROXIES` set to your proxy only, and `CORS_ALLOWED_ORIGINS`
      as short as possible.
- [ ] `MAIL_*` pointing to a real mail server, so verification and password
      reset emails are delivered.
- [ ] PHP, `laravel/framework` and `laravel/passport` at or above the minimum
      versions listed above.
- [ ] A database backup taken before `php artisan migrate --force`.
- [ ] At least one administrator created with `php artisan app:create-admin`,
      and no test accounts left.
- [ ] The database port not reachable from the internet.
