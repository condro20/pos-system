<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
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
     * Alur:
     * 1. Validasi request
     * 2. Mulai database transaction
     * 3. Generate invoice
     * 4. Lock product
     * 5. Cek stok
     * 6. Ambil harga dari database
     * 7. Hitung subtotal
     * 8. Kurangi stok
     * 9. Buat Sale
     * 10. Buat SaleDetail
     * 11. Commit transaction
     */
    public function store(Request $request)
    {
        /**
         * Validasi input dari frontend.
         *
         * Frontend hanya perlu mengirim:
         * - product_id
         * - qty
         * - payment_method
         *
         * Harga TIDAK dipercayakan kepada frontend.
         */
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
            $sale = DB::transaction(function () use ($validated) {

                /*
                 * Generate nomor invoice.
                 *
                 * Contoh:
                 * INV-20260916-0001
                 * INV-20260916-0002
                 */
                $datePrefix = now()->format('Ymd');

                $lastSale = Sale::query()
                    ->where(
                        'invoice_no',
                        'like',
                        "INV-{$datePrefix}-%"
                    )
                    ->orderByDesc('id')
                    ->first();

                $sequence = $lastSale
                    ? ((int) substr($lastSale->invoice_no, -4)) + 1
                    : 1;

                $invoiceNo = 'INV-' .
                    $datePrefix .
                    '-' .
                    str_pad(
                        $sequence,
                        4,
                        '0',
                        STR_PAD_LEFT
                    );

                $grandTotal = 0.00;
                $saleDetails = [];

                /**
                 * Proses setiap item cart.
                 */
                foreach ($validated['items'] as $item) {

                    /**
                     * Lock row product selama transaction berlangsung.
                     *
                     * Ini mencegah dua transaksi secara bersamaan
                     * menggunakan stok yang sama.
                     */
                    $product = Product::query()
                        ->whereKey($item['product_id'])
                        ->lockForUpdate()
                        ->first();

                    /**
                     * Secara teori tidak null karena sudah divalidasi
                     * dengan exists:products,id.
                     *
                     * Tetapi tetap dicek untuk keamanan.
                     */
                    if (!$product) {
                        throw new \RuntimeException(
                            'Produk tidak ditemukan.'
                        );
                    }

                    /**
                     * Cast quantity dan nilai database
                     * ke tipe numerik yang konsisten.
                     */
                    $qty = (float) $item['qty'];

                    $stock = (float) $product->stock;

                    $purchasePrice = (float) $product->purchase_price;

                    $sellingPrice = (float) $product->selling_price;

                    /**
                     * Validasi quantity.
                     */
                    if ($qty <= 0) {
                        throw new \RuntimeException(
                            "Jumlah produk {$product->name} harus lebih dari 0."
                        );
                    }

                    /**
                     * Validasi stok SETELAH lockForUpdate().
                     *
                     * Ini penting untuk mencegah stok menjadi negatif.
                     */
                    if ($stock < $qty) {
                        throw new \RuntimeException(
                            "Stok {$product->name} tidak mencukupi. " .
                            "Stok tersedia: {$stock}, " .
                            "jumlah diminta: {$qty}."
                        );
                    }

                    /**
                     * Hitung subtotal menggunakan harga
                     * yang berasal dari database.
                     *
                     * Harga dari frontend tidak digunakan.
                     */
                    $subtotal = round(
                        $qty * $sellingPrice,
                        2
                    );

                    $grandTotal += $subtotal;

                    /**
                     * Simpan harga historis transaksi.
                     *
                     * Walaupun harga produk berubah nanti,
                     * transaksi lama tetap menggunakan harga
                     * saat transaksi terjadi.
                     */
                    $saleDetails[] = [
                        'product_id' => $product->id,
                        'quantity' => round($qty, 3),
                        'purchase_price' => round(
                            $purchasePrice,
                            2
                        ),
                        'selling_price' => round(
                            $sellingPrice,
                            2
                        ),
                        'subtotal' => $subtotal,
                    ];

                    /**
                     * Kurangi stok.
                     *
                     * Dibulatkan 3 angka karena database:
                     * DECIMAL(10,3)
                     */
                    $product->stock = round(
                        $stock - $qty,
                        3
                    );

                    $product->save();
                }

                /**
                 * Pastikan total akhir memiliki
                 * maksimal 2 angka desimal.
                 */
                $grandTotal = round($grandTotal, 2);

                /**
                 * Buat header transaksi.
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

                /**
                 * Buat detail transaksi.
                 */
                $sale->saleDetails()->createMany(
                    $saleDetails
                );

                /**
                 * DB::transaction() otomatis melakukan commit
                 * apabila seluruh proses berhasil.
                 */
                return $sale;
            });

            /**
             * Response sukses untuk frontend.
             */
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil.',
                'print_url' => route(
                    'pos.receipt',
                    $sale->id
                ),
            ]);

        } catch (\Throwable $e) {

            /**
             * DB::transaction() otomatis rollback
             * apabila terjadi exception.
             */

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

        /**
         * Whitelist kolom sorting agar input user
         * tidak dapat digunakan sebagai SQL column arbitrary.
         */
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

        /**
         * Whitelist arah sorting.
         */
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

        /**
         * Query transaksi.
         */
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

        /**
         * Search invoice atau nama kasir.
         */
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

        /**
         * Sorting.
         */
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

        /**
         * Pagination.
         */
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