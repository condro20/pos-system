<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_sequences', function (Blueprint $table) {
            $table->id();

            /*
             * sale
             * purchase
             */
            $table->string('type', 20);

            /*
             * Tanggal sequence.
             *
             * Contoh:
             * 2026-09-18
             */
            $table->date('transaction_date');

            /*
             * Nomor terakhir yang sudah digunakan.
             */
            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            /*
             * Satu sequence untuk satu jenis
             * transaksi pada satu tanggal.
             */
            $table->unique(
                ['type', 'transaction_date'],
                'transaction_sequences_type_date_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_sequences');
    }
};