<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds stock-keeping fields to products and stores prices as decimal(10,2).
 *
 * - sku: nullable (products created before this migration have none), unique.
 * - stock: unsigned integer, 0 for existing products.
 * - status: active, draft or archived; existing products become active.
 * - price: string -> decimal(10,2). Before the type change, every existing
 *   price is rewritten as a plain decimal with two places. Values that are
 *   not numbers become 0.00, negative values become 0.00, and values above
 *   99999999.99 (the largest decimal(10,2) value) become 99999999.99. The API
 *   has always required a numeric price, so these cases should not occur.
 *
 * products.category_id is left as it is (a signed integer without a foreign
 * key): categories are deleted without checking for products, so existing
 * databases can contain products whose category no longer exists, and a
 * foreign key would make this migration fail on them.
 */
return new class extends Migration
{
    private const MAX_PRICE = 99999999.99;

    public function up(): void
    {
        $this->normalisePrices();

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->unique()->after('name');
            $table->unsignedInteger('stock')->default(0)->after('price');
            $table->string('status', 16)->default('active')->index()->after('stock');
        });
    }

    /**
     * Removes the new columns and stores prices as strings again ("12.50").
     * Prices changed by the up() data step are not restored.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropIndex(['status']);
            $table->dropColumn(['sku', 'stock', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('price')->change();
        });
    }

    private function normalisePrices(): void
    {
        DB::table('products')->select(['id', 'price'])->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $price = self::normalisedPrice($row->price);

                    if ($price !== $row->price) {
                        DB::table('products')->where('id', $row->id)->update(['price' => $price]);
                    }
                }
            });
    }

    private static function normalisedPrice(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : $value;
        $number = is_numeric($value) ? round((float) $value, 2) : 0.0;
        $number = max(0.0, min(self::MAX_PRICE, $number));

        return number_format($number, 2, '.', '');
    }
};
