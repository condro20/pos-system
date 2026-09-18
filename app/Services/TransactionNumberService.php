<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TransactionNumberService
{
    /**
     * Generate nomor transaksi secara atomic dan concurrency-safe.
     *
     * Jenis yang tersedia:
     * - sale     => INV-YYYYMMDD-0001
     * - purchase => PO-YYYYMMDD-0001
     *
     * Method ini WAJIB dipanggil di dalam DB::transaction().
     */
    public function generate(
        string $type,
        ?CarbonInterface $date = null
    ): string {
        $type = strtolower(trim($type));

        $config = match ($type) {
            'sale' => [
                'prefix' => 'INV',
            ],

            'purchase' => [
                'prefix' => 'PO',
            ],

            default => throw new InvalidArgumentException(
                "Jenis nomor transaksi '{$type}' tidak didukung."
            ),
        };

        $date = $date ?: now();

        $transactionDate = $date->format('Y-m-d');

        /*
         * Pastikan row sequence untuk kombinasi:
         *
         * type + tanggal
         *
         * sudah tersedia.
         *
         * insertOrIgnore aman digunakan bersama UNIQUE:
         * (type, transaction_date)
         *
         * Jika dua transaction masuk bersamaan:
         *
         * Request A -> insert
         * Request B -> conflict -> ignore
         *
         * Setelah itu keduanya mengambil row yang sama
         * dengan lockForUpdate().
         */
        DB::table('transaction_sequences')->insertOrIgnore([
            'type' => $type,
            'transaction_date' => $transactionDate,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Lock row sequence.
         *
         * Hanya satu transaction yang dapat mengubah
         * last_number pada satu waktu.
         */
        $sequence = DB::table('transaction_sequences')
            ->where('type', $type)
            ->where('transaction_date', $transactionDate)
            ->lockForUpdate()
            ->first();

        if (!$sequence) {
            throw new RuntimeException(
                'Sequence transaksi tidak ditemukan.'
            );
        }

        $nextNumber = ((int) $sequence->last_number) + 1;

        /*
         * Format nomor menggunakan 4 digit.
         *
         * 0001
         * 0002
         * ...
         * 9999
         */
        if ($nextNumber > 9999) {
            throw new RuntimeException(
                "Nomor transaksi {$config['prefix']}-{$date->format('Ymd')} telah mencapai batas 9999."
            );
        }

        DB::table('transaction_sequences')
            ->where('id', $sequence->id)
            ->update([
                'last_number' => $nextNumber,
                'updated_at' => now(),
            ]);

        return sprintf(
            '%s-%s-%04d',
            $config['prefix'],
            $date->format('Ymd'),
            $nextNumber
        );
    }
}