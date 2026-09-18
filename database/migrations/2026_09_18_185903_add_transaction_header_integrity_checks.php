<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE sales
            ADD CONSTRAINT sales_subtotal_non_negative
            CHECK (subtotal >= 0)
        ');

        DB::statement('
            ALTER TABLE sales
            ADD CONSTRAINT sales_discount_non_negative
            CHECK (discount >= 0)
        ');

        DB::statement('
            ALTER TABLE sales
            ADD CONSTRAINT sales_grand_total_non_negative
            CHECK (grand_total >= 0)
        ');

        DB::statement('
            ALTER TABLE purchases
            ADD CONSTRAINT purchases_grand_total_non_negative
            CHECK (grand_total >= 0)
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE sales
            DROP CONSTRAINT IF EXISTS sales_subtotal_non_negative
        ');

        DB::statement('
            ALTER TABLE sales
            DROP CONSTRAINT IF EXISTS sales_discount_non_negative
        ');

        DB::statement('
            ALTER TABLE sales
            DROP CONSTRAINT IF EXISTS sales_grand_total_non_negative
        ');

        DB::statement('
            ALTER TABLE purchases
            DROP CONSTRAINT IF EXISTS purchases_grand_total_non_negative
        ');
    }
};