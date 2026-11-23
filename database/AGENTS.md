# AGENTS.md: database (`database/`)

Applies in addition to the [root AGENTS.md](../AGENTS.md). The schema is
described in [ARCHITECTURE.md](../ARCHITECTURE.md) ("Data model").

## Migrations

- Never edit a migration that has been released; add a new one. Name it
  like the existing ones (`2026_11_23_000004_drop_unused_role_and_social_account_tables.php`).
- Migrations must be safe for existing data: running databases have real
  rows. Convert or clean values before changing a column type (see
  `2026_11_23_000003_add_inventory_fields_to_products_table.php`), and give
  new non-null columns a default.
- Write a working `down()` that restores the previous schema. If data
  cannot come back, say so in a comment and in the upgrade notes.
- The code must work on MySQL 9.7 (development and production) and on
  SQLite (tests). Test a migration that changes data in
  `tests/Feature/Database/`, as `ProductInventoryMigrationTest` and
  `DropUnusedTablesMigrationTest` do.
- Breaking or data-changing migrations need upgrade and rollback steps for
  existing installations in [docs/upgrading.md](../docs/upgrading.md).
- The migrations do not create foreign key constraints; keep that unless the
  task is to add them.

## Factories

- One factory per model in `factories/`. Factory states describe roles and
  situations (for example `User::factory()->admin()` and `->unverified()`).
- Factory passwords are for tests only and never appear in seeders.

## Seeders

- Seeders only load the optional demo catalogue (`php artisan db:seed`).
  They must never create user accounts; `UsersTableSeeder` is empty on
  purpose. The first administrator is created with
  `php artisan app:create-admin`.
- Seeders must be idempotent: running them again must not add duplicates or
  change existing rows. Use `firstOrCreate()` keyed on a natural key (name,
  SKU), as the existing seeders do. `SeederTest` checks this.
- Demo product images live in `public/images/products/` (sources in
  `resources/images/products/`) and must stay under about 60 KB each.

## Commands

Run these in the `app` container (`docker compose exec -T app ...`). Do not
run `migrate:fresh`, `migrate:reset`, `migrate:rollback` or `db:wipe`
against the development database unless the user asks; the PHPUnit suite
uses its own in-memory SQLite database and needs none of them.

- `php artisan migrate` applies new migrations.
- `php artisan db:seed` loads the demo catalogue.
