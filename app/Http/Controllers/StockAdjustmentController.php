<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Throwable;

class StockAdjustmentController extends Controller
{
    /**
     * Riwayat Stock Adjustment
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        /*
        |--------------------------------------------------------------------------
        | Whitelist Sorting
        |--------------------------------------------------------------------------
        | Jangan langsung memasukkan input user ke orderBy.
        */
        $allowedSorts = [
            'created_at',
            'system_stock',
            'physical_stock',
            'adjustment',
            'reason',
        ];

        $sort = $request->input('sort', 'created_at');

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower(
            $request->input('direction', 'desc')
        );

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query = StockAdjustment::query()
            ->with(['product', 'user'])
            ->select('stock_adjustments.*')
            ->leftJoin(
                'products',
                'stock_adjustments.product_id',
                '=',
                'products.id'
            )
            ->leftJoin(
                'users',
                'stock_adjustments.user_id',
                '=',
                'users.id'
            );

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'products.name',
                    'ilike',
                    "%{$search}%"
                )
                ->orWhere(
                    'products.barcode',
                    'ilike',
                    "%{$search}%"
                )
                ->orWhere(
                    'users.name',
                    'ilike',
                    "%{$search}%"
                )
                ->orWhere(
                    'stock_adjustments.reason',
                    'ilike',
                    "%{$search}%"
                );
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Sorting
        |--------------------------------------------------------------------------
        */
        if ($sort === 'product') {
            $query->orderBy(
                'products.name',
                $direction
            );
        } elseif ($sort === 'user') {
            $query->orderBy(
                'users.name',
                $direction
            );
        } else {
            $query->orderBy(
                'stock_adjustments.' . $sort,
                $direction
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Pagination
        |--------------------------------------------------------------------------
        */
        $stockAdjustments = $query
            ->paginate(10)
            ->withQueryString();

        return Inertia::render(
            'StockAdjustments/Index',
            [
                'stockAdjustments' => $stockAdjustments,
                'filters' => [
                    'search' => $search,
                    'sort' => $sort,
                    'direction' => $direction,
                ],
            ]
        );
    }

    /**
     * Form Stock Adjustment
     */
    public function create()
    {
        /*
        |--------------------------------------------------------------------------
        | Hanya produk aktif
        |--------------------------------------------------------------------------
        | Product menggunakan SoftDeletes, sehingga produk yang sudah
        | dihapus tidak ditampilkan sebagai pilihan opname.
        */
        $products = Product::query()
            ->orderBy('name')
            ->get([
                'id',
                'barcode',
                'name',
                'unit',
                'stock',
            ]);

        return Inertia::render(
            'StockAdjustments/Create',
            [
                'products' => $products,
            ]
        );
    }

    /**
     * Simpan Stock Adjustment
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
            ],

            'physical_stock' => [
                'required',
                'numeric',
                'min:0',
            ],

            'reason' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        try {
            DB::transaction(function () use ($validated) {

                /*
                |--------------------------------------------------------------------------
                | LOCK PRODUCT
                |--------------------------------------------------------------------------
                | Selalu ambil stok TERBARU dari database.
                |
                | Jangan menggunakan stok yang dikirim dari browser sebagai
                | sumber kebenaran.
                */
                $product = Product::query()
                    ->whereKey($validated['product_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    throw new \RuntimeException(
                        'Produk tidak ditemukan atau sudah tidak aktif.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Normalisasi angka
                |--------------------------------------------------------------------------
                */
                $systemStock = round(
                    (float) $product->stock,
                    3
                );

                $physicalStock = round(
                    (float) $validated['physical_stock'],
                    3
                );

                /*
                |--------------------------------------------------------------------------
                | Validasi ulang
                |--------------------------------------------------------------------------
                */
                if ($physicalStock < 0) {
                    throw new \RuntimeException(
                        'Stok fisik tidak boleh kurang dari 0.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Hitung selisih
                |--------------------------------------------------------------------------
                |
                | adjustment positif  = stok bertambah
                | adjustment negatif  = stok berkurang
                | adjustment 0        = tidak ada perubahan
                */
                $adjustment = round(
                    $physicalStock - $systemStock,
                    3
                );

                /*
                |--------------------------------------------------------------------------
                | Simpan histori
                |--------------------------------------------------------------------------
                */
                StockAdjustment::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),

                    'system_stock' => $systemStock,
                    'physical_stock' => $physicalStock,
                    'adjustment' => $adjustment,

                    'reason' => trim(
                        $validated['reason']
                    ),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Update stok master
                |--------------------------------------------------------------------------
                */
                $product->stock = $physicalStock;
                $product->save();
            });

            return redirect()
                ->route('stock-adjustments.index')
                ->with(
                    'success',
                    'Stok berhasil disesuaikan.'
                );

        } catch (Throwable $e) {

            report($e);

            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'error' =>
                        'Gagal menyimpan penyesuaian stok. Silakan coba lagi.',
                ]);
        }
    }
}