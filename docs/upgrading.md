# Upgrading

Upgrade and rollback steps for releases that change configuration, the
database or behaviour. Read every step before you upgrade an existing
installation, and take a backup first. For a fresh installation, follow the
[README](../README.md#quick-start-with-docker) instead.

## Upgrading from the Laravel 8 version

This release has breaking changes. Read all of the steps before upgrading an
existing installation.

1. **Back up the database** (for example
   `mysqldump --single-transaction --routines -u<user> -p <database> > backup.sql`)
   and keep a copy of `storage/`, `public/upload/` and your current image or
   commit.
2. **Update `.env`** against the new `.env.example`. `DB_USERNAME` must not be
   `root`. Set `DB_ROOT_PASSWORD`. `MIX_*` and `PUSHER_*` variables are gone.
   Laravel 11+ names include `CACHE_STORE` (was `CACHE_DRIVER`) and
   `BROADCAST_CONNECTION`. `APP_DEBUG` now defaults to `false`.
3. **Runtime**: PHP 8.5, MySQL 9.7 LTS (or 8.4 LTS) and Node 26 are required. The Docker stack
   now uses a single Apache + PHP container on internal port 8080 (published as
   `APP_PORT`, default 8000) instead of nginx + php-fpm. Adminer and the public
   MySQL port were removed.
4. **Laravel Mix has been replaced by Vite.** `webpack.mix.js`,
   `public/js/app.js`, `public/css/app.css` and `mix-manifest.json` are no
   longer used. Run `npm ci && npm run build` (assets go to `public/build`).
5. **Registration no longer creates admins.** Every self-registered account is
   a regular user. The seeded `admin@gmail.com` / `123456` account has been
   removed from the seeders. Create administrators with
   `php artisan app:create-admin`. If an old database still contains that
   account, delete it or change its password immediately.
6. **Passwords** must now be at least 12 characters. Existing passwords keep
   working until they are changed.
7. **Uploads** moved from `public/upload` to the Laravel `public` disk
   (`storage/app/public/uploads`), served via `public/storage`. Run
   `php artisan storage:link`. Copy any existing images you want to keep into
   `storage/app/public/uploads/`. Only jpg, png and webp images are accepted.
8. **Passport 13**: run `php artisan migrate` to apply the new OAuth and
   session tables, `php artisan passport:keys` if the keys are missing, and
   `php artisan passport:client --personal` for personal access tokens.
   Existing tokens may need to be re-issued. Databases created by Passport 10
   use integer client IDs: set `PASSPORT_CLIENT_UUIDS=false`, and run
   `php artisan passport:hash` once to hash existing client secrets.
   The SPA's `laravel_token` cookie now carries a claim derived from the
   password, and cookies without it are rejected, so a dashboard tab that was
   open during the upgrade is sent to `/login` once and, while its session is
   still valid, straight back to the dashboard with a new cookie.
9. **Email verification is now required** for self-registered accounts, so
   configure `MAIL_*` with a working mail server. The migration
   `2026_11_23_000002_mark_existing_users_as_verified` marks all existing
   accounts as verified, so nobody is locked out by the upgrade.
10. **Uploaded images are re-encoded** on the server. This removes metadata
    such as EXIF and GPS data and anything hidden inside the file. Images
    that cannot be decoded are rejected.
11. **Products have new columns and a numeric price.** The migration
    `2026_11_23_000003_add_inventory_fields_to_products_table` adds `sku`
    (unique, empty for existing products), `stock` (0) and `status`
    (`active`), and stores `price` as `decimal(10,2)`. Before converting, it
    rewrites existing prices: values that are not numbers or are negative
    become 0.00, and values above 99999999.99 are capped. A SKU is required
    when creating a product. Rolling back removes the SKU, stock and status
    values. Running `php artisan db:seed` adds the new demo catalogue but does
    not remove the old car demo products, categories or tags, and the old car
    photos are no longer shipped. Delete those rows in the UI if you do not
    want them.
12. **Unused tables are dropped.** The migration
    `2026_11_23_000004_drop_unused_role_and_social_account_tables` drops the
    `roles`, `role_user` and `social_accounts` tables, which no code has
    used. If you added rows to them yourself, export those rows first.
    Rolling the migration back recreates the tables as they were defined
    before, but empty.
13. **Migrate** with `php artisan migrate --force`, then
    `php artisan optimize:clear`.

**Rollback**: redeploy the previous image or commit, then restore the database
backup taken in step 1 (`mysql ... < backup.sql`) and the saved `storage/`.
Migrations are not guaranteed to be reversible, so restore the backup instead
of relying on `migrate:rollback`.

**Existing Docker data**: the old stack (MySQL 8.0.17, root password
`123456`, `mysql_native_password`) stored data in the `dbdata` volume. The new
stack uses a fresh `db_data` volume with a non-root application user, so the
old data is not picked up automatically. Before switching, dump it from the old
stack (`mysqldump` as in step 1, run on the old checkout), then start the new
stack and import it:
`docker compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "$MYSQL_DATABASE"' < backup.sql`.
Remove the old volume and its weak root password once you have verified the
upgrade.

**Docker stack already on MySQL 8.4**: the compose file now uses MySQL 9.7
LTS. MySQL upgrades an 8.4 data directory to 9.7 automatically the first time
the new container starts (an upgrade between two LTS releases is supported).
Take a dump first, as in step 1, because the upgraded data directory cannot be
opened by MySQL 8.4 again. To roll back, start a fresh `mysql:8.4` volume and
import the dump.
