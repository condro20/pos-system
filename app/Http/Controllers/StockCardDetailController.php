<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockAdjustment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockCardDetailController extends Controller
{
    /**
     * Menampilkan detail transaksi dari Stock Card.
     *
     * Type:
     * - purchase
     * - sale
     * - adjustment
     */
    public function show(
        Request $request,
        string $type,
        string $reference
    ): JsonResponse {
        $type = strtolower($type);

        if (!in_array($type, [
            'purchase',
            'sale',
            'adjustment'
        ], true)) {
            return response()->json([
                'message' => 'Jenis transaksi tidak valid.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | PURCHASE / PO
        |--------------------------------------------------------------------------
        */

        if ($type === 'purchase') {

            $purchase = Purchase::with([
                'supplier',
                'user',
                'purchaseDetails.product',
            ])
                ->where('invoice_no', $reference)
                ->firstOrFail();

            return response()->json([
                'type' => 'purchase',

                'title' => 'Detail Purchase Order',

                'reference' => $purchase->invoice_no,

                'date' => $purchase->created_at,

                'supplier' =>
                    $purchase->supplier?->name ?? '-',

                'user' =>
                    $purchase->user?->name ?? '-',

                'grand_total' =>
                    (float) $purchase->grand_total,

                'items' =>
                    $purchase->purchaseDetails
                        ->map(function ($detail) {

                            return [
                                'id' => $detail->id,

                                'product' =>
                                    $detail->product?->name
                                    ?? 'Produk Dihapus',

                                'unit' =>
                                    $detail->product?->unit
                                    ?? '-',

                                'quantity' =>
                                    (float) $detail->quantity,

                                'price' =>
                                    (float) $detail->price,

                                'subtotal' =>
                                    (float) $detail->subtotal,
                            ];
                        })
                        ->values(),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | SALE / INVOICE
        |--------------------------------------------------------------------------
        */

        if ($type === 'sale') {

            $sale = Sale::with([
                'user',
                'customer',
                'saleDetails.product',
            ])
                ->where('invoice_no', $reference)
                ->firstOrFail();

            return response()->json([
                'type' => 'sale',

                'title' =>
                    'Detail Transaksi Penjualan',

                'reference' =>
                    $sale->invoice_no,

                'date' =>
                    $sale->created_at,

                'cashier' =>
                    $sale->user?->name
                    ?? 'Kasir Dihapus',

                'customer' =>
                    $sale->customer?->name
                    ?? '-',

                'payment_method' =>
                    $sale->payment_method,

                'subtotal' =>
                    (float) $sale->subtotal,

                'discount' =>
                    (float) $sale->discount,

                'grand_total' =>
                    (float) $sale->grand_total,

                'items' =>
                    $sale->saleDetails
                        ->map(function ($detail) {

                            return [
                                'id' => $detail->id,

                                'product' =>
                                    $detail->product?->name
                                    ?? 'Produk Dihapus',

                                'unit' =>
                                    $detail->product?->unit
                                    ?? '-',

                                'quantity' =>
                                    (float) $detail->quantity,

                                'price' =>
                                    (float) $detail->selling_price,

                                'subtotal' =>
                                    (float) $detail->subtotal,
                            ];
                        })
                        ->values(),
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | STOCK ADJUSTMENT
        |--------------------------------------------------------------------------
        */

        $adjustmentId = (int) preg_replace(
            '/^ADJ-/i',
            '',
            $reference
        );

        if ($adjustmentId <= 0) {

            return response()->json([
                'message' =>
                    'Referensi adjustment tidak valid.',
            ], 422);
        }

        $adjustment = StockAdjustment::with([
            'product',
            'user',
        ])
            ->findOrFail($adjustmentId);

        return response()->json([
            'type' => 'adjustment',

            'title' =>
                'Detail Stock Adjustment',

            'reference' =>
                'ADJ-' . $adjustment->id,

            'date' =>
                $adjustment->created_at,

            'product' =>
                $adjustment->product?->name
                ?? 'Produk Dihapus',

            'unit' =>
                $adjustment->product?->unit
                ?? '-',

            'user' =>
                $adjustment->user?->name
                ?? '-',

            'system_stock' =>
                (float) $adjustment->system_stock,

            'physical_stock' =>
                (float) $adjustment->physical_stock,

            'adjustment' =>
                (float) $adjustment->adjustment,

            'reason' =>
                $adjustment->reason,
        ]);
    }
}