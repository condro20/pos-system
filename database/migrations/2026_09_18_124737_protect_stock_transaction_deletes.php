<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SALE DETAILS
        |--------------------------------------------------------------------------
        */

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->restrictOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | PURCHASE DETAILS
        |--------------------------------------------------------------------------
        */

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | KEMBALIKAN SALE DETAILS
        |--------------------------------------------------------------------------
        */

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);

            $table->foreign('sale_id')
                ->references('id')
                ->on('sales')
                ->cascadeOnDelete();
        });

        /*
        |--------------------------------------------------------------------------
        | KEMBALIKAN PURCHASE DETAILS
        |--------------------------------------------------------------------------
        */

        Schema::table('purchase_details', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);

            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->cascadeOnDelete();
        });
    }
};