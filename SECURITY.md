# Security Policy

## Supported versions

| Version | Supported |
| --- | --- |
| `main` (Laravel 13, PHP 8.5, Passport 13, Vue 3 + Vite) | Yes |
| Laravel 8 / Vue 2 / Laravel Mix releases (before the 2026 hardening) | No. Upgrade, see [README](README.md#upgrading-from-the-laravel-8-version) |

Only the latest commit on `main` receives security fixes. This is a starter
template: once you fork it, keeping your copy patched is your responsibility.

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

Report privately through GitHub Security Advisories:
**Security** tab, then **Report a vulnerability**
(`https://github.com/AnowarCST/laravel-vue-crud-starter/security/advisories/new`).

Include affected version or commit, reproduction steps or a proof of concept,
and the impact you observed. You should get an acknowledgement within 7 days.
Fixes are developed in a private advisory fork and disclosed together with the
release. Credit is given unless you ask otherwise.

Vulnerabilities in Laravel, Passport, Vue or other dependencies should be
reported to those projects directly.

## Security best practices

### Secrets and `.env`

- `.env` is gitignored and must never be committed, baked into an image, or
  pasted into issues. `.env.example` only holds empty placeholders. The Docker
  build context excludes `.env*`.
- Generate long random values for `DB_PASSWORD` and `DB_ROOT_PASSWORD`, for
  example with `openssl rand -base64 32`. `docker compose` refuses to start
  while they are empty. The application connects as `DB_USERNAME`, never as
  `root`.
- **APP_KEY** encrypts sessions and cookies. Generate it once with
  `php artisan key:generate`. To rotate it, move the old key to
  `APP_PREVIOUS_KEYS` (comma separated), set a new `APP_KEY`, and deploy. After
  every user has re-authenticated, remove the old key.
- **Passport keys**: `php artisan passport:keys` writes
  `storage/oauth-private.key` and `storage/oauth-public.key` (gitignored). In
  production, provide them through `PASSPORT_PRIVATE_KEY`/`PASSPORT_PUBLIC_KEY`
  or a mounted secret. Rotating them (`php artisan passport:keys --force`)
  invalidates every issued access token, so plan it as a forced logout. Rotate
  immediately if a key may have leaked.

### Accounts and administrators

- There is **no seeded default administrator**. The old
  `admin@gmail.com` / `123456` account no longer exists. Create admins
  interactively:

  ```bash
  docker compose exec app php artisan app:create-admin
  ```

  The password is prompted for, never accepted as a command-line argument, so
  it does not end up in shell history or process lists.
- Public registration always creates **non-admin** users. A role field sent in
  the request is ignored.
- Passwords must be at least 12 characters. Only administrators can list,
  create, update or delete accounts. Administrators cannot change their own
  role or delete their own account.
- Self-registered accounts must verify their email address before they can
  use the dashboard or the API. Changing the email address on the profile page
  requires verifying the new address. Accounts created by an administrator or
  with `app:create-admin` are verified automatically. If a user mistypes a new
  address, an administrator can correct it on the Users page, and the user can
  then request a new verification link.
- Changing a password revokes the user's OAuth access and refresh tokens and
  signs out their other browser sessions.

### File uploads

- Only images (`jpg`, `jpeg`, `png`, `webp`, max 5 MB, max 4096x4096 px) are
  accepted. Type, extension and content are all validated server side.
- Every accepted image is decoded and re-encoded on the server (same format).
  This removes metadata such as EXIF and GPS data and any data hidden inside
  or appended to the file. Files that cannot be decoded as images are
  rejected.
- Files are stored with random, server-generated names on the `public` disk
  (`storage/app/public/uploads`). They are served through the `public/storage`
  symlink created by `php artisan storage:link`.
- Apache never executes anything from upload directories (PHP engine off).
  Script, HTML, SVG and JS files there are denied, and all responses carry
  `X-Content-Type-Options: nosniff` so an uploaded file cannot be sniffed as
  HTML.

### HTTP headers and transport

- Laravel middleware sends a nonce-based Content-Security-Policy,
  `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy` and `Cross-Origin-Opener-Policy`. It adds HSTS only on
  HTTPS requests. Apache adds `nosniff` for static files, hides its version
  (`ServerTokens Prod`, `ServerSignature Off`) and disables `TRACE`.
- CORS only allows `APP_URL` plus any origins you list in
  `CORS_ALLOWED_ORIGINS`.
- If you run behind a load balancer or reverse proxy, set `TRUSTED_PROXIES` to
  its address or CIDR only.

### Rate limiting

Named rate limiters protect login (10/min per IP, plus Laravel's lockout after
5 failures per email and IP), registration (5/min per IP), password reset
(5/min per IP and 10/hour per email) and the API (60/min per user or IP).

### Dependency auditing

- CI (`.github/workflows/ci.yml`) runs `composer validate --strict`,
  `composer audit`, `npm audit --audit-level=moderate`, Pint, Larastan
  (level 9), ESLint, Prettier, the PHPUnit suite and a production Vite build
  on every pull request. The production Docker build with an `/up` smoke
  test (`.github/workflows/docker.yml`), the end-to-end tests and the ZAP
  scan are opt-in; see [docs/ci-cd.md](docs/ci-cd.md).
- CodeQL (`.github/workflows/codeql.yml`) scans JavaScript/TypeScript and the
  GitHub Actions workflows on every PR and weekly.
- Dependabot (`.github/dependabot.yml`) proposes weekly grouped updates for
  Composer, npm, Docker images and GitHub Actions. Third-party actions are
  pinned to full commit SHAs.
- Locally: `composer audit` and `npm audit` (or `npm run audit:security`).

### Docker hardening

- Base images are pinned to exact patch tags.
- The production image (`docker/Dockerfile`, target `prod`) contains no
  Composer, Node, git or dev dependencies. Application code is owned by root
  and read-only. Only `storage/` and `bootstrap/cache/` are writable by
  `www-data`.
- Apache runs entirely as `www-data` on port 8080. Only `public/` is served.
  `vendor/`, `storage/`, dotfiles and backup/config file types are denied.
- `docker-compose.yml` binds ports to `127.0.0.1` only, does not publish MySQL
  (opt in with `docker-compose.db-port.yml`), drops Linux capabilities and
  sets `no-new-privileges`.
- OPcache in production does not re-check file timestamps, so code changes
  require a new image.

### Production checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=warning` (or higher)
- [ ] Unique `APP_KEY`, strong DB credentials, Passport keys provisioned as
      secrets
- [ ] HTTPS terminates in front of the app, `APP_URL` uses `https://`, and
      `SESSION_SECURE_COOKIE=true`
- [ ] `TRUSTED_PROXIES` set to your proxy only; `CORS_ALLOWED_ORIGINS` minimal
- [ ] `php artisan config:cache` (the prod image runs `php artisan optimize`
      on start; set `LARAVEL_OPTIMIZE=false` to skip)
- [ ] `php artisan storage:link` (done at image build) and only `storage/` plus
      `bootstrap/cache/` writable by the web user
- [ ] Database backup taken before `php artisan migrate --force`
- [ ] At least one admin created with `php artisan app:create-admin`; no test
      accounts left
- [ ] `MAIL_*` points to a real mail server, so verification and password
      reset emails are delivered
- [ ] Database port not reachable from the internet
- [ ] CI green, including `composer audit` and `npm audit`
