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
    public function index(Request $request)
    {
        $search = trim(
            (string) $request->input('search', '')
        );

        $allowedSorts = [
            'id',
            'created_at',
            'system_stock',
            'physical_stock',
            'adjustment',
            'reason',
            'product',
            'user',
        ];

        $sort = $request->input(
            'sort',
            'created_at'
        );

        if (!in_array(
            $sort,
            $allowedSorts,
            true
        )) {
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

        $query = StockAdjustment::query()
            ->with([
                'product',
                'user',
            ])
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

    public function create()
    {
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => [
                'required',
                'integer',
                'exists:products,id',
            ],

            'physical_stock' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,3',
            ],

            'reason' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $reason = trim(
            $validated['reason']
        );

        if ($reason === '') {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors([
                    'reason' =>
                        'Alasan Stock Adjustment tidak boleh kosong.',
                ]);
        }

        try {
            DB::transaction(
                function () use (
                    $validated,
                    $reason
                ) {
                    $product = Product::query()
                        ->whereKey(
                            $validated['product_id']
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new \RuntimeException(
                            'Produk tidak ditemukan atau sudah tidak aktif.'
                        );
                    }

                    /*
                     * Ambil system stock langsung dari DB
                     * setelah row di-lock.
                     */
                    $systemStock = round(
                        (float) $product->stock,
                        3
                    );

                    $physicalStock = round(
                        (float) $validated['physical_stock'],
                        3
                    );

                    if ($systemStock < 0) {
                        throw new \RuntimeException(
                            'System stock tidak valid.'
                        );
                    }

                    if ($physicalStock < 0) {
                        throw new \RuntimeException(
                            'Stok fisik tidak boleh negatif.'
                        );
                    }

                    $adjustment = round(
                        $physicalStock -
                        $systemStock,
                        3
                    );

                    StockAdjustment::create([
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'system_stock' => $systemStock,
                        'physical_stock' => $physicalStock,
                        'adjustment' => $adjustment,
                        'reason' => $reason,
                    ]);

                    /*
                     * Stock master menjadi physical stock.
                     */
                    $product->stock = $physicalStock;
                    $product->save();
                },
                5
            );

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
                        'Gagal menyimpan penyesuaian stok. ' .
                        $e->getMessage(),
                ]);
        }
    }
}