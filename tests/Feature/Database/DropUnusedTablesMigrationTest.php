<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 2026_11_23_000004_drop_unused_role_and_social_account_tables.
 */
class DropUnusedTablesMigrationTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_11_23_000004_drop_unused_role_and_social_account_tables.php';

    private const TABLES = ['roles', 'role_user', 'social_accounts'];

    /**
     * The migrations that originally created the tables.
     */
    private const ORIGINAL_MIGRATIONS = [
        'database/migrations/2019_12_09_044534_create_roles_table.php',
        'database/migrations/2019_12_09_082630_create_role_user_table.php',
        'database/migrations/2019_12_09_094511_create_social_accounts_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // A fresh in-memory database for every test.
        $this->assertSame(0, Artisan::call('migrate'));
    }

    public function test_the_tables_are_gone_after_migrating(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
        $this->assertTrue(Schema::hasTable('users'));
    }

    public function test_rolling_back_recreates_the_tables_as_originally_defined(): void
    {
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--path' => self::MIGRATION]));
        $recreated = $this->schema();

        // Build the same tables with the original migrations and compare.
        $this->dropTables();
        foreach (self::ORIGINAL_MIGRATIONS as $path) {
            $this->assertSame(0, Artisan::call('migrate:refresh', ['--path' => $path]));
        }

        $this->assertSame($this->schema(), $recreated);
        $this->assertSame(['id', 'name', 'description', 'created_at', 'updated_at'], Schema::getColumnListing('roles'));
        $this->assertSame(['id', 'role_id', 'user_id', 'created_at', 'updated_at'], Schema::getColumnListing('role_user'));
        $this->assertSame(['user_id', 'provider_user_id', 'provider', 'created_at', 'updated_at'], Schema::getColumnListing('social_accounts'));
    }

    public function test_migrating_again_after_a_rollback_drops_the_tables(): void
    {
        $this->assertSame(0, Artisan::call('migrate:rollback', ['--path' => self::MIGRATION]));
        $this->assertTrue(Schema::hasTable('roles'));

        $this->assertSame(0, Artisan::call('migrate'));

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
    }

    /**
     * Columns and indexes of the three tables.
     *
     * @return array<string, array{columns: mixed, indexes: mixed}>
     */
    private function schema(): array
    {
        $schema = [];
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
            $schema[$table] = ['columns' => Schema::getColumns($table), 'indexes' => Schema::getIndexes($table)];
        }

        return $schema;
    }

    private function dropTables(): void
    {
        foreach (self::TABLES as $table) {
            Schema::drop($table);
        }
    }
}
