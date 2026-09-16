<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->onDelete('restrict');
            $table->string('barcode')->nullable()->unique();
            $table->string('name');
            $table->string('unit');
            $table->decimal('stock', 10, 3)->default(0); // Desimal untuk satuan pecahan (cth: 1.5 kg)
            $table->decimal('purchase_price', 15, 2);
            $table->decimal('selling_price', 15, 2);
            $table->timestamps();
            $table->softDeletes(); // Menambahkan kolom deleted_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
