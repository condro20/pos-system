<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // Siapa yang opname
            $table->decimal('system_stock', 10, 3); // Stok sebelum disesuaikan
            $table->decimal('physical_stock', 10, 3); // Stok asli di lapangan
            $table->decimal('adjustment', 10, 3); // Selisih (Bisa minus/plus)
            $table->string('reason'); // Alasan (Tumpah, Hilang, dll)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
