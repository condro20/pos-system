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
     * - database transaction
     * - transaction number concurrency-safe
     * - product row lock
     * - deterministic lock ordering
     * - stock validation setelah lock
     * - historical price
     * - atomic header + detail
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
                'min:0.01',
            ],

            'payment_method' => [
                'required',
                'string',
                Rule::in(['Cash']),
            ],
        ]);

        try {
            $sale = DB::transaction(function () use (
                $validated,
                $transactionNumberService
            ) {
                /*
                 * ==========================================================
                 * NORMALISASI URUTAN ITEM
                 * ==========================================================
                 *
                 * Semua transaction akan mengunci product berdasarkan
                 * product_id dari kecil ke besar.
                 *
                 * Ini mengurangi kemungkinan deadlock:
                 *
                 * Transaction A:
                 * product 1 -> product 2
                 *
                 * Transaction B:
                 * product 2 -> product 1
                 *
                 * Dengan sorting:
                 *
                 * A: 1 -> 2
                 * B: 1 -> 2
                 */
                $items = collect($validated['items'])
                    ->sortBy('product_id')
                    ->values()
                    ->all();

                /*
                 * ==========================================================
                 * GENERATE NOMOR INVOICE
                 * ==========================================================
                 *
                 * Nomor dibuat di dalam transaction.
                 *
                 * Jika transaction gagal:
                 * sequence juga rollback.
                 *
                 * Artinya nomor tidak akan "loncat" karena transaction
                 * yang gagal.
                 */
                $invoiceNo = $transactionNumberService->generate(
                    'sale'
                );

                $grandTotal = 0.00;

                $saleDetails = [];

                /*
                 * ==========================================================
                 * LOCK + VALIDASI + UPDATE STOK
                 * ==========================================================
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
                     * PENTING:
                     *
                     * Pengecekan stok dilakukan SETELAH lockForUpdate().
                     */
                    if ($stock < $qty) {
                        throw new \RuntimeException(
                            "Stok {$product->name} tidak mencukupi. " .
                            "Stok tersedia: {$stock}, " .
                            "jumlah diminta: {$qty}."
                        );
                    }

                    $subtotal = round(
                        $qty * $sellingPrice,
                        2
                    );

                    $grandTotal += $subtotal;

                    /*
                     * Simpan harga historis.
                     */
                    $saleDetails[] = [
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'purchase_price' => $purchasePrice,
                        'selling_price' => $sellingPrice,
                        'subtotal' => $subtotal,
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

                $grandTotal = round(
                    $grandTotal,
                    2
                );

                /*
                 * ==========================================================
                 * CREATE SALE HEADER
                 * ==========================================================
                 */
                $sale = Sale::create([
                    'invoice_no' => $invoiceNo,
                    'user_id' => Auth::id(),
                    'customer_id' => null,
                    'subtotal' => $grandTotal,
                    'discount' => 0,
                    'grand_total' => $grandTotal,
                    'payment_method' => $validated['payment_method'],
                ]);

                /*
                 * ==========================================================
                 * CREATE SALE DETAILS
                 * ==========================================================
                 */
                $sale->saleDetails()->createMany(
                    $saleDetails
                );

                /*
                 * Jika seluruh proses berhasil:
                 *
                 * - sequence commit
                 * - stock commit
                 * - sale commit
                 * - details commit
                 */
                return $sale;
            }, 5);

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

        return view('receipt', [
            'sale' => $sale,
        ]);
    }

    /**
     * Menampilkan history transaksi.
     */
    public function history(Request $request)
    {
        $search = $request->input('search');

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

        $allowedDirections = [
            'asc',
            'desc',
        ];

        $direction = strtolower(
            $request->input(
                'direction',
                'desc'
            )
        );

        if (!in_array($direction, $allowedDirections, true)) {
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

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'sales.invoice_no',
                    'ilike',
                    "%{$search}%"
                )->orWhere(
                    'users.name',
                    'ilike',
                    "%{$search}%"
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