<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
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
        $search = trim($request->input('search', ''));

        $allowedSorts = [
            'created_at',
            'invoice_no',
            'grand_total',
            'supplier',
        ];

        $sort = $request->input('sort', 'created_at');

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower($request->input('direction', 'desc'));

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
            ->when($search !== '', function ($query) use ($search) {
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
            });

        if ($sort === 'supplier') {
            $query->orderBy('suppliers.name', $direction);
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
     * Purchase:
     * - membuat nomor PO
     * - membuat Purchase
     * - membuat PurchaseDetail
     * - menambah stok
     * - memperbarui harga beli terakhir
     */
    public function store(Request $request)
    {
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
                'min:0.01',
            ],

            'items.*.price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        try {
            $purchase = DB::transaction(function () use ($validated) {

                /*
                 * ==========================================
                 * GENERATE NOMOR PO
                 * Format:
                 *
                 * PO-YYYYMMDD-0001
                 * ==========================================
                 */

                $datePrefix = now()->format('Ymd');

                $lastPurchase = Purchase::where(
                    'invoice_no',
                    'like',
                    "PO-{$datePrefix}-%"
                )
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $sequence = 1;

                if ($lastPurchase) {
                    $sequence =
                        ((int) substr(
                            $lastPurchase->invoice_no,
                            -4
                        )) + 1;
                }

                $invoiceNo =
                    'PO-' .
                    $datePrefix .
                    '-' .
                    str_pad(
                        $sequence,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );

                /*
                 * ==========================================
                 * PROSES ITEM
                 * ==========================================
                 */

                $grandTotal = 0;

                $purchaseDetails = [];

                foreach ($validated['items'] as $item) {

                    $product = Product::where(
                        'id',
                        $item['product_id']
                    )
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

                    $grandTotal += $subtotal;

                    /*
                     * Simpan detail Purchase
                     */
                    $purchaseDetails[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'price' => $price,
                        'subtotal' => $subtotal,
                    ];

                    /*
                     * ==========================================
                     * UPDATE STOK
                     *
                     * Purchase = stok bertambah
                     * ==========================================
                     */

                    $currentStock = (float) $product->stock;

                    $product->stock = round(
                        $currentStock + $qty,
                        3
                    );

                    /*
                     * Harga beli terakhir
                     */
                    $product->purchase_price = $price;

                    $product->save();
                }

                /*
                 * ==========================================
                 * CREATE PURCHASE
                 * ==========================================
                 */

                $purchase = Purchase::create([
                    'invoice_no' => $invoiceNo,
                    'user_id' => Auth::id(),
                    'supplier_id' => $validated['supplier_id'],
                    'grand_total' => round(
                        $grandTotal,
                        2
                    ),
                ]);

                /*
                 * ==========================================
                 * CREATE PURCHASE DETAILS
                 * ==========================================
                 */

                $purchase->purchaseDetails()
                    ->createMany(
                        $purchaseDetails
                    );

                return $purchase;
            });

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
     * Output:
     * resources/views/purchases/print.blade.php
     *
     * Format:
     * A3 Landscape
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