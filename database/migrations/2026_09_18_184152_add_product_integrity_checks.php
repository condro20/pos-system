<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE products
            ADD CONSTRAINT products_stock_non_negative
            CHECK (stock >= 0)
        ');

        DB::statement('
            ALTER TABLE products
            ADD CONSTRAINT products_purchase_price_non_negative
            CHECK (purchase_price >= 0)
        ');

        DB::statement('
            ALTER TABLE products
            ADD CONSTRAINT products_selling_price_non_negative
            CHECK (selling_price >= 0)
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE products
            DROP CONSTRAINT IF EXISTS products_stock_non_negative
        ');

        DB::statement('
            ALTER TABLE products
            DROP CONSTRAINT IF EXISTS products_purchase_price_non_negative
        ');

        DB::statement('
            ALTER TABLE products
            DROP CONSTRAINT IF EXISTS products_selling_price_non_negative
        ');
    }
};