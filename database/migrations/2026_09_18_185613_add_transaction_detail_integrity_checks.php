<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * SALE DETAILS
         */
        DB::statement('
            ALTER TABLE sale_details
            ADD CONSTRAINT sale_details_quantity_positive
            CHECK (quantity > 0)
        ');

        DB::statement('
            ALTER TABLE sale_details
            ADD CONSTRAINT sale_details_purchase_price_non_negative
            CHECK (purchase_price >= 0)
        ');

        DB::statement('
            ALTER TABLE sale_details
            ADD CONSTRAINT sale_details_selling_price_non_negative
            CHECK (selling_price >= 0)
        ');

        DB::statement('
            ALTER TABLE sale_details
            ADD CONSTRAINT sale_details_subtotal_non_negative
            CHECK (subtotal >= 0)
        ');

        /*
         * PURCHASE DETAILS
         */
        DB::statement('
            ALTER TABLE purchase_details
            ADD CONSTRAINT purchase_details_quantity_positive
            CHECK (quantity > 0)
        ');

        DB::statement('
            ALTER TABLE purchase_details
            ADD CONSTRAINT purchase_details_price_non_negative
            CHECK (price >= 0)
        ');

        DB::statement('
            ALTER TABLE purchase_details
            ADD CONSTRAINT purchase_details_subtotal_non_negative
            CHECK (subtotal >= 0)
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE sale_details
            DROP CONSTRAINT IF EXISTS sale_details_quantity_positive
        ');

        DB::statement('
            ALTER TABLE sale_details
            DROP CONSTRAINT IF EXISTS sale_details_purchase_price_non_negative
        ');

        DB::statement('
            ALTER TABLE sale_details
            DROP CONSTRAINT IF EXISTS sale_details_selling_price_non_negative
        ');

        DB::statement('
            ALTER TABLE sale_details
            DROP CONSTRAINT IF EXISTS sale_details_subtotal_non_negative
        ');

        DB::statement('
            ALTER TABLE purchase_details
            DROP CONSTRAINT IF EXISTS purchase_details_quantity_positive
        ');

        DB::statement('
            ALTER TABLE purchase_details
            DROP CONSTRAINT IF EXISTS purchase_details_price_non_negative
        ');

        DB::statement('
            ALTER TABLE purchase_details
            DROP CONSTRAINT IF EXISTS purchase_details_subtotal_non_negative
        ');
    }
};