<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\TransactionNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PosController extends Controller
{
    /**
     * Menampilkan halaman POS.
     *
     * Hanya produk dengan stok > 0 yang ditampilkan.
     */
    public function index()
    {
        $products = Product::query()
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->get();

        return Inertia::render('POS/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Memproses checkout POS.
     *
     * Proteksi:
     * - validasi request
     * - normalisasi duplicate product
     * - database transaction
     * - transaction number concurrency-safe
     * - product row lock
     * - deterministic lock ordering
     * - stock validation setelah lock
     * - historical price
     * - atomic header + detail
     * - header/detail reconciliation
     * - deadlock retry
     */
    public function store(
        Request $request,
        TransactionNumberService $transactionNumberService
    ) {
        $validated = $request->validate([
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

            'payment_method' => [
                'required',
                'string',
                Rule::in(['Cash']),
            ],
        ]);

        try {
            $sale = DB::transaction(
                function () use (
                    $validated,
                    $transactionNumberService
                ) {
                    /*
                     * ======================================================
                     * NORMALISASI ITEM
                     * ======================================================
                     *
                     * Product yang sama hanya diproses satu kali.
                     *
                     * Contoh:
                     *
                     * Product A - 2
                     * Product A - 3
                     *
                     * menjadi:
                     *
                     * Product A - 5
                     */
                    $items = collect($validated['items'])
                        ->groupBy('product_id')
                        ->map(function (
                            $productItems,
                            $productId
                        ) {
                            $totalQty = $productItems->sum(
                                fn ($item) => (float) $item['qty']
                            );

                            return [
                                'product_id' => (int) $productId,
                                'qty' => round(
                                    $totalQty,
                                    3
                                ),
                            ];
                        })
                        ->sortBy('product_id')
                        ->values()
                        ->all();

                    if (empty($items)) {
                        throw new \RuntimeException(
                            'Item transaksi tidak boleh kosong.'
                        );
                    }

                    /*
                     * ======================================================
                     * GENERATE NOMOR INVOICE
                     * ======================================================
                     */
                    $invoiceNo = $transactionNumberService->generate(
                        'sale'
                    );

                    $subtotal = 0;

                    $saleDetails = [];

                    /*
                     * ======================================================
                     * LOCK + VALIDASI + UPDATE STOK
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

                        $stock = round(
                            (float) $product->stock,
                            3
                        );

                        $purchasePrice = round(
                            (float) $product->purchase_price,
                            2
                        );

                        $sellingPrice = round(
                            (float) $product->selling_price,
                            2
                        );

                        if ($qty <= 0) {
                            throw new \RuntimeException(
                                "Jumlah produk {$product->name} harus lebih dari 0."
                            );
                        }

                        /*
                         * Stock dicek SETELAH lock.
                         */
                        if ($stock < $qty) {
                            throw new \RuntimeException(
                                "Stok {$product->name} tidak mencukupi. " .
                                "Stok tersedia: {$stock}, " .
                                "jumlah diminta: {$qty}."
                            );
                        }

                        /*
                         * Hitung subtotal berdasarkan harga
                         * saat transaksi terjadi.
                         */
                        $detailSubtotal = round(
                            $qty * $sellingPrice,
                            2
                        );

                        $subtotal = round(
                            $subtotal + $detailSubtotal,
                            2
                        );

                        /*
                         * Simpan historical price.
                         *
                         * Harga produk boleh berubah di kemudian hari,
                         * tetapi histori transaksi tetap benar.
                         */
                        $saleDetails[] = [
                            'product_id' => $product->id,
                            'quantity' => $qty,
                            'purchase_price' => $purchasePrice,
                            'selling_price' => $sellingPrice,
                            'subtotal' => $detailSubtotal,
                        ];

                        /*
                         * Kurangi stok.
                         */
                        $product->stock = round(
                            $stock - $qty,
                            3
                        );

                        $product->save();
                    }

                    /*
                     * ======================================================
                     * CREATE SALE HEADER
                     * ======================================================
                     *
                     * Saat ini POS belum memiliki discount input.
                     *
                     * Oleh karena itu:
                     *
                     * subtotal    = subtotal detail
                     * discount    = 0
                     * grand_total = subtotal
                     */
                    $subtotal = round(
                        $subtotal,
                        2
                    );

                    $discount = 0.00;

                    $grandTotal = round(
                        $subtotal - $discount,
                        2
                    );

                    if ($grandTotal < 0) {
                        throw new \RuntimeException(
                            'Grand total transaksi tidak valid.'
                        );
                    }

                    $sale = Sale::create([
                        'invoice_no' => $invoiceNo,
                        'user_id' => Auth::id(),
                        'customer_id' => null,
                        'subtotal' => $subtotal,
                        'discount' => $discount,
                        'grand_total' => $grandTotal,
                        'payment_method' => $validated['payment_method'],
                    ]);

                    /*
                     * ======================================================
                     * CREATE SALE DETAILS
                     * ======================================================
                     */
                    $sale->saleDetails()->createMany(
                        $saleDetails
                    );

                    /*
                     * ======================================================
                     * RECONCILIATION HEADER ↔ DETAIL
                     * ======================================================
                     *
                     * Pastikan jumlah subtotal seluruh detail sama
                     * dengan subtotal header.
                     *
                     * Gunakan integer cents untuk menghindari
                     * perbandingan float secara langsung.
                     */
                    $detailSubtotalCents = (int) round(
                        (float) $sale
                            ->saleDetails()
                            ->sum('subtotal') * 100
                    );

                    $headerSubtotalCents = (int) round(
                        $subtotal * 100
                    );

                    if (
                        $detailSubtotalCents
                        !== $headerSubtotalCents
                    ) {
                        throw new \RuntimeException(
                            'Subtotal transaksi tidak konsisten dengan detail penjualan.'
                        );
                    }

                    /*
                     * ======================================================
                     * RECONCILIATION GRAND TOTAL
                     * ======================================================
                     */
                    $storedGrandTotalCents = (int) round(
                        (float) $sale->grand_total * 100
                    );

                    $expectedGrandTotalCents = (int) round(
                        $grandTotal * 100
                    );

                    if (
                        $storedGrandTotalCents
                        !== $expectedGrandTotalCents
                    ) {
                        throw new \RuntimeException(
                            'Grand total transaksi tidak konsisten.'
                        );
                    }

                    /*
                     * ======================================================
                     * VALIDASI DISCOUNT
                     * ======================================================
                     *
                     * Saat ini discount selalu 0.
                     */
                    $storedDiscountCents = (int) round(
                        (float) $sale->discount * 100
                    );

                    $expectedDiscountCents = (int) round(
                        $discount * 100
                    );

                    if (
                        $storedDiscountCents
                        !== $expectedDiscountCents
                    ) {
                        throw new \RuntimeException(
                            'Discount transaksi tidak konsisten.'
                        );
                    }

                    /*
                     * Semua berhasil.
                     *
                     * Commit:
                     * - transaction sequence
                     * - product stock
                     * - sale header
                     * - sale details
                     */
                    return $sale;
                },
                5
            );

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil.',
                'print_url' => route(
                    'pos.receipt',
                    $sale->id
                ),
            ]);
        } catch (\Throwable $e) {
            Log::error('POS checkout gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menampilkan struk transaksi.
     */
    public function receipt(Sale $sale)
    {
        $sale->load([
            'saleDetails.product',
            'user',
        ]);

        return view('pos.receipt', [
            'sale' => $sale,
        ]);
    }

    /**
     * Menampilkan history transaksi.
     */
    public function history(Request $request)
    {
        $search = trim(
            $request->input('search', '')
        );

        $allowedSorts = [
            'created_at',
            'invoice_no',
            'grand_total',
            'payment_method',
            'cashier',
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

        if (!in_array(
            $direction,
            ['asc', 'desc'],
            true
        )) {
            $direction = 'desc';
        }

        $query = Sale::query()
            ->with([
                'user',
                'saleDetails.product',
            ])
            ->select('sales.*')
            ->leftJoin(
                'users',
                'sales.user_id',
                '=',
                'users.id'
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'sales.invoice_no',
                    'ilike',
                    '%' . $search . '%'
                )
                ->orWhere(
                    'users.name',
                    'ilike',
                    '%' . $search . '%'
                );
            });
        }

        if ($sort === 'cashier') {
            $query->orderBy(
                'users.name',
                $direction
            );
        } else {
            $query->orderBy(
                'sales.' . $sort,
                $direction
            );
        }

        $sales = $query
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('POS/History', [
            'sales' => $sales,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }
}