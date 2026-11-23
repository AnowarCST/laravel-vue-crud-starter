<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 2026_11_23_000003_add_inventory_fields_to_products_table on rows created
 * before it existed (price stored as a string).
 */
class ProductInventoryMigrationTest extends TestCase
{
    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        // A fresh in-memory database per test; run every migration, then
        // undo this one to get the old products table.
        $this->assertSame(0, Artisan::call('migrate'));
        $migration = require database_path('migrations/2026_11_23_000003_add_inventory_fields_to_products_table.php');
        $this->assertInstanceOf(Migration::class, $migration);
        $this->migration = $migration;
        $this->migrate('down');
    }

    private function migrate(string $direction): void
    {
        $callback = [$this->migration, $direction];
        $this->assertIsCallable($callback);
        $callback();
    }

    public function test_legacy_prices_are_converted_and_new_columns_get_defaults(): void
    {
        $this->assertFalse(Schema::hasColumns('products', ['sku', 'stock', 'status']));

        $prices = [
            '99508694' => '99508694.00',
            '19.95' => '19.95',
            ' 7 ' => '7.00',
            '12.345' => '12.35',
            '100000000' => '99999999.99',
            '-5' => '0.00',
            'abc' => '0.00',
            '' => '0.00',
        ];
        foreach (array_keys($prices) as $i => $price) {
            DB::table('products')->insert([
                'id' => $i + 1, 'name' => 'Legacy '.$i, 'description' => 'Old row',
                'price' => (string) $price, 'category_id' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->migrate('up');

        $this->assertTrue(Schema::hasColumns('products', ['sku', 'stock', 'status']));
        $rows = DB::table('products')->orderBy('id')->get();
        $this->assertCount(count($prices), $rows);
        foreach (array_values($prices) as $i => $expected) {
            $row = $rows[$i];
            $this->assertNotNull($row);
            $this->assertIsNumeric($row->price);
            $this->assertSame($expected, number_format((float) $row->price, 2, '.', ''), 'Row '.($i + 1));
            $this->assertNull($row->sku);
            $this->assertEquals(0, $row->stock);
            $this->assertSame('active', $row->status);
        }

        // Prices now sort numerically.
        $max = DB::table('products')->max('price');
        $this->assertIsNumeric($max);
        $this->assertSame('99999999.99', number_format((float) $max, 2, '.', ''));
    }

    public function test_down_removes_the_new_columns_and_keeps_prices_as_strings(): void
    {
        $this->migrate('up');
        DB::table('products')->insert([
            'name' => 'New', 'sku' => 'NEW-1', 'description' => 'D', 'price' => '12.50', 'stock' => 3,
            'status' => 'draft', 'category_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->migrate('down');

        $this->assertFalse(Schema::hasColumns('products', ['sku', 'stock', 'status']));
        $this->assertSame('varchar', Schema::getColumnType('products', 'price'));
        $this->assertEquals(12.5, DB::table('products')->value('price'));

        // And up again, so the migration can be re-applied after a rollback.
        $this->migrate('up');
        $this->assertTrue(Schema::hasColumns('products', ['sku', 'stock', 'status']));
    }
}
