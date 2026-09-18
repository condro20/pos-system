<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
use App\Services\TransactionNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Throwable;

class PurchaseController extends Controller
{
    /**
     * Menampilkan riwayat Purchase / Barang Masuk.
     */
    public function index(Request $request)
    {
        $search = trim(
            $request->input('search', '')
        );

        $allowedSorts = [
            'created_at',
            'invoice_no',
            'grand_total',
            'supplier',
        ];

        $sort = $request->input(
            'sort',
            'created_at'
        );

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(
            $request->input(
                'direction',
                'desc'
            )
        );

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query = Purchase::with([
                'supplier',
                'user',
                'purchaseDetails.product',
            ])
            ->select('purchases.*')
            ->leftJoin(
                'suppliers',
                'purchases.supplier_id',
                '=',
                'suppliers.id'
            )
            ->when(
                $search !== '',
                function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where(
                            'purchases.invoice_no',
                            'ilike',
                            '%' . $search . '%'
                        )
                        ->orWhere(
                            'suppliers.name',
                            'ilike',
                            '%' . $search . '%'
                        );
                    });
                }
            );

        if ($sort === 'supplier') {
            $query->orderBy(
                'suppliers.name',
                $direction
            );
        } else {
            $query->orderBy(
                'purchases.' . $sort,
                $direction
            );
        }

        $purchases = $query
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Form input Purchase.
     */
    public function create()
    {
        return Inertia::render('Purchases/Create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    /**
     * Menyimpan Purchase.
     *
     * Proteksi:
     * - validasi quantity dan harga
     * - normalisasi duplicate product
     * - nomor PO concurrency-safe
     * - product row lock
     * - deterministic lock ordering
     * - atomic stock + header + detail
     * - header/detail reconciliation
     * - deadlock retry
     */
    public function store(
        Request $request,
        TransactionNumberService $transactionNumberService
    ) {
        $validated = $request->validate([
            'supplier_id' => [
                'required',
                'integer',
                'exists:suppliers,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'items.*.qty' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,3',
            ],

            'items.*.price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        try {
            $purchase = DB::transaction(
                function () use (
                    $validated,
                    $transactionNumberService
                ) {
                    /*
                     * ======================================================
                     * NORMALISASI ITEM
                     * ======================================================
                     *
                     * Jika product yang sama dikirim lebih dari sekali,
                     * quantity digabung menjadi satu baris.
                     *
                     * Harga terakhir digunakan.
                     */
                    $items = collect($validated['items'])
                        ->groupBy('product_id')
                        ->map(function ($productItems, $productId) {
                            $totalQty = $productItems->sum(
                                fn ($item) => (float) $item['qty']
                            );

                            $lastItem = $productItems->last();

                            return [
                                'product_id' => (int) $productId,
                                'qty' => round(
                                    $totalQty,
                                    3
                                ),
                                'price' => round(
                                    (float) $lastItem['price'],
                                    2
                                ),
                            ];
                        })
                        ->sortBy('product_id')
                        ->values()
                        ->all();

                    if (empty($items)) {
                        throw new \RuntimeException(
                            'Item pembelian tidak boleh kosong.'
                        );
                    }

                    /*
                     * ======================================================
                     * GENERATE NOMOR PO
                     * ======================================================
                     */
                    $invoiceNo = $transactionNumberService->generate(
                        'purchase'
                    );

                    $grandTotal = 0;

                    $purchaseDetails = [];

                    /*
                     * ======================================================
                     * LOCK + UPDATE PRODUCT
                     * ======================================================
                     */
                    foreach ($items as $item) {
                        $product = Product::query()
                            ->whereKey($item['product_id'])
                            ->lockForUpdate()
                            ->first();

                        if (!$product) {
                            throw new \RuntimeException(
                                'Produk tidak ditemukan.'
                            );
                        }

                        $qty = round(
                            (float) $item['qty'],
                            3
                        );

                        $price = round(
                            (float) $item['price'],
                            2
                        );

                        if ($qty <= 0) {
                            throw new \RuntimeException(
                                "Quantity produk {$product->name} harus lebih dari 0."
                            );
                        }

                        if ($price < 0) {
                            throw new \RuntimeException(
                                "Harga beli produk {$product->name} tidak valid."
                            );
                        }

                        $subtotal = round(
                            $qty * $price,
                            2
                        );

                        $grandTotal = round(
                            $grandTotal + $subtotal,
                            2
                        );

                        $purchaseDetails[] = [
                            'product_id' => $product->id,
                            'quantity' => $qty,
                            'price' => $price,
                            'subtotal' => $subtotal,
                        ];

                        /*
                         * ==================================================
                         * UPDATE STOCK
                         * ==================================================
                         */
                        $currentStock = round(
                            (float) $product->stock,
                            3
                        );

                        $newStock = round(
                            $currentStock + $qty,
                            3
                        );

                        $product->stock = $newStock;

                        /*
                         * Harga beli terakhir.
                         */
                        $product->purchase_price = $price;

                        $product->save();
                    }

                    /*
                     * ======================================================
                     * CREATE PURCHASE HEADER
                     * ======================================================
                     */
                    $grandTotal = round(
                        $grandTotal,
                        2
                    );

                    $purchase = Purchase::create([
                        'invoice_no' => $invoiceNo,
                        'user_id' => Auth::id(),
                        'supplier_id' => $validated['supplier_id'],
                        'grand_total' => $grandTotal,
                    ]);

                    /*
                     * ======================================================
                     * CREATE PURCHASE DETAILS
                     * ======================================================
                     */
                    $purchase->purchaseDetails()
                        ->createMany(
                            $purchaseDetails
                        );

                    /*
                     * ======================================================
                     * RECONCILIATION HEADER ↔ DETAIL
                     * ======================================================
                     *
                     * Pastikan:
                     *
                     * SUM(detail.subtotal)
                     * =
                     * purchase.grand_total
                     */
                    $detailSubtotalCents = (int) round(
                        (float) $purchase
                            ->purchaseDetails()
                            ->sum('subtotal') * 100
                    );

                    $headerTotalCents = (int) round(
                        $grandTotal * 100
                    );

                    if ($detailSubtotalCents !== $headerTotalCents) {
                        throw new \RuntimeException(
                            'Total Purchase tidak konsisten dengan detail Purchase.'
                        );
                    }

                    /*
                     * Pastikan nilai header juga sesuai dengan
                     * nilai yang dihitung controller.
                     */
                    $storedTotalCents = (int) round(
                        (float) $purchase->grand_total * 100
                    );

                    if ($storedTotalCents !== $headerTotalCents) {
                        throw new \RuntimeException(
                            'Grand total Purchase tidak konsisten.'
                        );
                    }

                    return $purchase;
                },
                5
            );

            return redirect()
                ->route('purchases.index')
                ->with(
                    'success',
                    "Pembelian {$purchase->invoice_no} berhasil dicatat. Stok berhasil diperbarui."
                );
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'error' =>
                        'Gagal memproses pembelian. ' .
                        $e->getMessage(),
                ]);
        }
    }

    /**
     * Cetak Purchase Order.
     *
     * Format:
     * A5 Landscape
     */
    public function print(Purchase $purchase)
    {
        $purchase->load([
            'supplier',
            'user',
            'purchaseDetails.product',
        ]);

        return view(
            'purchases.print',
            [
                'purchase' => $purchase,
            ]
        );
    }
}