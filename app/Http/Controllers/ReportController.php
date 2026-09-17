<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function sales(Request $request)
    {
        // 1. Tangkap parameter filter, pencarian, dan pengurutan
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at'); // Default terbaru di atas
        $direction = $request->input('direction', 'desc');

        // 2. Buat Query Dasar dengan Eager Loading, Join, dan Subquery Profit
        $baseQuery = \App\Models\Sale::with(['user', 'saleDetails.product'])
            ->select('sales.*') 
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            // 👇 TRIK PENGURUTAN: Tambahkan perhitungan profit langsung di dalam query database
            ->addSelect(['profit_calc' => \App\Models\SaleDetail::selectRaw('SUM((selling_price - purchase_price) * quantity)')
                ->whereColumn('sale_id', 'sales.id')
            ])
            ->whereBetween('sales.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->when($search, function ($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('sales.invoice_no', 'ilike', "%{$search}%")
                          ->orWhere('users.name', 'ilike', "%{$search}%");
                });
            });

        // 3. Hitung Ringkasan (Total Pendapatan, Laba, dan Transaksi)
        $summaryQuery = clone $baseQuery;
        $allFilteredSales = $summaryQuery->get(); 
        
        $totalRevenue = $allFilteredSales->sum('grand_total');
        $totalTransactions = $allFilteredSales->count();
        $totalProfit = $allFilteredSales->sum('profit_calc'); // Bisa langsung pakai hasil hitungan DB

        // 4. Terapkan Sorting pada Query Dasar
        if ($sort === 'cashier') {
            $baseQuery->orderBy('users.name', $direction);
        } elseif ($sort === 'profit') {
            $baseQuery->orderBy('profit_calc', $direction); // Sort menggunakan alias dari subquery
        } else {
            $baseQuery->orderBy('sales.' . $sort, $direction);
        }

        // 5. Eksekusi Pagination
        $paginatedSales = $baseQuery->paginate(15)->withQueryString();

        // 6. Format ulang data (Gunakan through agar pagination tidak rusak)
        $formattedSales = $paginatedSales->through(function ($sale) {
            return [
                'id' => $sale->id,
                'invoice_no' => $sale->invoice_no,
                'date' => \Carbon\Carbon::parse($sale->created_at)->format('d/m/Y H:i'),
                'cashier' => $sale->user ? $sale->user->name : 'Kasir Dihapus',
                'grand_total' => $sale->grand_total,
                'profit' => $sale->profit_calc ?? 0, // Ambil dari hasil subquery
                'payment_method' => $sale->payment_method,
                'sale_details' => $sale->saleDetails, 
            ];
        });

        return \Inertia\Inertia::render('Reports/Sales', [
            'sales' => $formattedSales,
            'summary' => [
                'revenue' => $totalRevenue,
                'profit' => $totalProfit,
                'transactions' => $totalTransactions
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }

    public function stockCard(Request $request)
    {
        // =========================================================
        // 1. FILTER
        // =========================================================
        $selectedProductId = $request->input('product_id');

        $startDate = $request->input(
            'start_date',
            Carbon::now()->startOfMonth()->format('Y-m-d')
        );

        $endDate = $request->input(
            'end_date',
            Carbon::now()->endOfMonth()->format('Y-m-d')
        );

        $search = trim((string) $request->input('search', ''));

        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');

        // Whitelist sorting agar aman
        $allowedSorts = [
            'date',
            'type',
            'reference',
            'description',
            'in',
            'out',
            'balance',
        ];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'date';
        }

        $direction = $direction === 'asc' ? 'asc' : 'desc';


        // =========================================================
        // 2. DAFTAR PRODUK
        // =========================================================
        // Jangan menggunakan where('is_active', true)
        // karena tabel products Anda tidak mempunyai kolom is_active.
        $products = Product::query()
            ->orderBy('name')
            ->get([
                'id',
                'barcode',
                'name',
                'unit',
                'stock',
            ]);


        // =========================================================
        // 3. DEFAULT
        // =========================================================
        $movements = collect();

        $selectedProduct = null;

        $summary = [
            'opening_stock' => 0,
            'total_in' => 0,
            'total_out' => 0,
            'ending_stock' => 0,
        ];


        // =========================================================
        // 4. JIKA PRODUK DIPILIH
        // =========================================================
        if ($selectedProductId) {

            $selectedProduct = Product::find($selectedProductId);

            if ($selectedProduct) {

                $periodStart = Carbon::parse($startDate)->startOfDay();
                $periodEnd = Carbon::parse($endDate)->endOfDay();


                // =================================================
                // 5. PENJUALAN DALAM PERIODE
                // =================================================
                $sales = DB::table('sale_details')
                    ->join(
                        'sales',
                        'sale_details.sale_id',
                        '=',
                        'sales.id'
                    )
                    ->where(
                        'sale_details.product_id',
                        $selectedProductId
                    )
                    ->whereBetween(
                        'sales.created_at',
                        [
                            $periodStart,
                            $periodEnd
                        ]
                    )
                    ->select(
                        'sales.created_at as date',
                        'sales.invoice_no as reference',
                        'sale_details.quantity as quantity'
                    )
                    ->get()
                    ->map(function ($item) {

                        return [
                            'date' => $item->date,
                            'type' => 'Penjualan (Kasir)',
                            'reference' => $item->reference,
                            'in' => 0,
                            'out' => round((float) $item->quantity, 3),
                            'description' => 'Terjual ke pelanggan',
                            'priority' => 2,
                        ];
                    });


                // =================================================
                // 6. PEMBELIAN DALAM PERIODE
                // =================================================
                $purchases = DB::table('purchase_details')
                    ->join(
                        'purchases',
                        'purchase_details.purchase_id',
                        '=',
                        'purchases.id'
                    )
                    ->leftJoin(
                        'suppliers',
                        'purchases.supplier_id',
                        '=',
                        'suppliers.id'
                    )
                    ->where(
                        'purchase_details.product_id',
                        $selectedProductId
                    )
                    ->whereBetween(
                        'purchases.created_at',
                        [
                            $periodStart,
                            $periodEnd
                        ]
                    )
                    ->select(
                        'purchases.created_at as date',
                        'purchases.invoice_no as reference',
                        'purchase_details.quantity as quantity',
                        'suppliers.name as supplier'
                    )
                    ->get()
                    ->map(function ($item) {

                        return [
                            'date' => $item->date,
                            'type' => 'Pembelian (Restock)',
                            'reference' => $item->reference,
                            'in' => round((float) $item->quantity, 3),
                            'out' => 0,
                            'description' => 'Barang masuk dari ' .
                                ($item->supplier ?? 'Supplier'),
                            'priority' => 1,
                        ];
                    });


                // =================================================
                // 7. STOCK ADJUSTMENT / OPNAME
                // =================================================
                $adjustments = DB::table('stock_adjustments')
                    ->where(
                        'product_id',
                        $selectedProductId
                    )
                    ->whereBetween(
                        'created_at',
                        [
                            $periodStart,
                            $periodEnd
                        ]
                    )
                    ->select(
                        'created_at as date',
                        'id',
                        'adjustment',
                        'reason'
                    )
                    ->get()
                    ->map(function ($item) {

                        $adjustment = round(
                            (float) $item->adjustment,
                            3
                        );

                        return [
                            'date' => $item->date,
                            'type' => 'Stok Opname',
                            'reference' => 'ADJ-' . $item->id,
                            'in' => $adjustment > 0
                                ? $adjustment
                                : 0,
                            'out' => $adjustment < 0
                                ? abs($adjustment)
                                : 0,
                            'description' => $item->reason,
                            'priority' => 3,
                        ];
                    });


                // =================================================
                // 8. HITUNG TRANSAKSI SETELAH END DATE
                //    Untuk merekonstruksi stok pada akhir periode.
                // =================================================

                // Penjualan setelah periode
                $salesAfterEnd = (float) DB::table('sale_details')
                    ->join(
                        'sales',
                        'sale_details.sale_id',
                        '=',
                        'sales.id'
                    )
                    ->where(
                        'sale_details.product_id',
                        $selectedProductId
                    )
                    ->where(
                        'sales.created_at',
                        '>',
                        $periodEnd
                    )
                    ->sum('sale_details.quantity');


                // Pembelian setelah periode
                $purchasesAfterEnd = (float) DB::table('purchase_details')
                    ->join(
                        'purchases',
                        'purchase_details.purchase_id',
                        '=',
                        'purchases.id'
                    )
                    ->where(
                        'purchase_details.product_id',
                        $selectedProductId
                    )
                    ->where(
                        'purchases.created_at',
                        '>',
                        $periodEnd
                    )
                    ->sum('purchase_details.quantity');


                // Adjustment setelah periode
                $adjustmentsAfterEnd = (float) DB::table('stock_adjustments')
                    ->where(
                        'product_id',
                        $selectedProductId
                    )
                    ->where(
                        'created_at',
                        '>',
                        $periodEnd
                    )
                    ->sum('adjustment');


                // =================================================
                // 9. REKONSTRUKSI STOK AKHIR PERIODE
                // =================================================
                $currentStock = (float) $selectedProduct->stock;

                $stockAtEnd = $currentStock
                    + $salesAfterEnd
                    - $purchasesAfterEnd
                    - $adjustmentsAfterEnd;


                // =================================================
                // 10. TOTAL PERGERAKAN DALAM PERIODE
                // =================================================
                $totalPeriodIn =
                    $purchases->sum('in')
                    + $adjustments->sum('in');

                $totalPeriodOut =
                    $sales->sum('out')
                    + $adjustments->sum('out');

                $totalPeriodAdjustment =
                    $adjustments->sum('in')
                    - $adjustments->sum('out');


                // =================================================
                // 11. HITUNG SALDO AWAL
                // =================================================
                $openingStock = $stockAtEnd
                    - $purchases->sum('in')
                    + $sales->sum('out')
                    - $totalPeriodAdjustment;


                // =================================================
                // 12. GABUNG SEMUA TRANSAKSI
                // =================================================
                $allMovements = $sales
                    ->concat($purchases)
                    ->concat($adjustments)
                    ->values();


                // =================================================
                // 13. URUTKAN SECARA KRONOLOGIS
                //     Sebelum menghitung saldo berjalan.
                // =================================================
                $chronologicalMovements = $allMovements
                    ->sort(function ($a, $b) {

                        $dateCompare = Carbon::parse($a['date'])
                            <=> Carbon::parse($b['date']);

                        if ($dateCompare !== 0) {
                            return $dateCompare;
                        }

                        return ($a['priority'] ?? 99)
                            <=> ($b['priority'] ?? 99);
                    })
                    ->values();


                // =================================================
                // 14. HITUNG SALDO BERJALAN
                // =================================================
                $runningBalance = round($openingStock, 3);

                $chronologicalMovements = $chronologicalMovements
                    ->map(function ($item) use (&$runningBalance) {

                        $runningBalance +=
                            (float) $item['in']
                            - (float) $item['out'];

                        $item['balance'] = round(
                            $runningBalance,
                            3
                        );

                        $item['date_formatted'] = Carbon::parse(
                            $item['date']
                        )->format('d/m/Y H:i');

                        return $item;
                    })
                    ->values();


                // =================================================
                // 15. BUAT BARIS SALDO AWAL
                // =================================================
                $openingRow = collect([
                    [
                        'date' => $periodStart,
                        'date_formatted' => $periodStart
                            ->format('d/m/Y H:i'),
                        'type' => 'Saldo Awal',
                        'reference' => 'SALDO-AWAL',
                        'in' => 0,
                        'out' => 0,
                        'balance' => round(
                            $openingStock,
                            3
                        ),
                        'description' => 'Saldo awal periode',
                        'priority' => 0,
                    ]
                ]);


                // =================================================
                // 16. GABUNG SALDO AWAL + TRANSAKSI
                // =================================================
                $displayMovements = $openingRow
                    ->concat($chronologicalMovements)
                    ->values();


                // =================================================
                // 17. SEARCH
                // =================================================
                if ($search !== '') {

                    $searchLower = strtolower($search);

                    $displayMovements = $displayMovements
                        ->filter(function ($item) use ($searchLower) {

                            return
                                str_contains(
                                    strtolower(
                                        (string) $item['reference']
                                    ),
                                    $searchLower
                                )
                                ||
                                str_contains(
                                    strtolower(
                                        (string) $item['type']
                                    ),
                                    $searchLower
                                )
                                ||
                                str_contains(
                                    strtolower(
                                        (string) $item['description']
                                    ),
                                    $searchLower
                                );
                        })
                        ->values();
                }


                // =================================================
                // 18. SORT DISPLAY
                // =================================================
                $displayMovements = $displayMovements
                    ->sort(function ($a, $b) use ($sort, $direction) {

                        if ($sort === 'date') {

                            $result = Carbon::parse($a['date'])
                                <=> Carbon::parse($b['date']);

                        } elseif (
                            in_array(
                                $sort,
                                ['in', 'out', 'balance'],
                                true
                            )
                        ) {

                            $result =
                                ((float) $a[$sort])
                                <=>
                                ((float) $b[$sort]);

                        } else {

                            $result = strcasecmp(
                                (string) ($a[$sort] ?? ''),
                                (string) ($b[$sort] ?? '')
                            );
                        }

                        return $direction === 'desc'
                            ? -$result
                            : $result;
                    })
                    ->values();


                // =================================================
                // 19. SUMMARY
                // =================================================
                $summary = [
                    'opening_stock' => round(
                        $openingStock,
                        3
                    ),

                    'total_in' => round(
                        $totalPeriodIn,
                        3
                    ),

                    'total_out' => round(
                        $totalPeriodOut,
                        3
                    ),

                    'ending_stock' => round(
                        $stockAtEnd,
                        3
                    ),
                ];


                // =================================================
                // 20. PAGINATION
                // =================================================
                $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;

                $perPage = 15;

                $total = $displayMovements->count();

                $itemsForCurrentPage = $displayMovements
                    ->forPage($page, $perPage)
                    ->values();

                $paginatedMovements =
                    new \Illuminate\Pagination\LengthAwarePaginator(
                        $itemsForCurrentPage,
                        $total,
                        $perPage,
                        $page,
                        [
                            'path' => $request->url(),
                            'query' => $request->query(),
                        ]
                    );
            }
        }


        // =========================================================
        // 21. RETURN INERTIA
        // =========================================================
        return Inertia::render('Reports/StockCard', [

            'products' => $products,

            'movements' => $paginatedMovements ?? new \Illuminate\Pagination\LengthAwarePaginator(
                [],
                0,
                15,
                1,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            ),

            'selected_product' => $selectedProduct,

            'summary' => $summary,

            'filters' => [
                'product_id' => $selectedProductId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function topSelling(Request $request)
    {
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));
        
        $search = $request->input('search');
        $sort = $request->input('sort', 'total_qty'); // Default urut dari yang paling laku
        $direction = $request->input('direction', 'desc'); // Dari jumlah terbanyak ke terdikit

        // Gunakan Subquery untuk menghitung total kuantitas dan total omzet produk dalam rentang tanggal
        $query = \App\Models\Product::with('category')
            ->select('products.*')
            ->addSelect(['total_qty' => \App\Models\SaleDetail::selectRaw('COALESCE(SUM(sale_details.quantity), 0)')
                ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                ->whereColumn('sale_details.product_id', 'products.id')
                ->whereBetween('sales.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ])
            ->addSelect(['total_revenue' => \App\Models\SaleDetail::selectRaw('COALESCE(SUM(sale_details.subtotal), 0)')
                ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                ->whereColumn('sale_details.product_id', 'products.id')
                ->whereBetween('sales.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ])
            ->when($search, function ($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('products.name', 'ilike', "%{$search}%")
                          ->orWhere('products.barcode', 'ilike', "%{$search}%");
                });
            });

        // Logika Sorting Khusus
        if ($sort === 'category') {
            $query->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                  ->orderBy('categories.name', $direction);
        } else {
            $query->orderBy($sort, $direction);
        }

        // Ambil 10 data per halaman
        $topProducts = $query->paginate(10)->withQueryString();

        return \Inertia\Inertia::render('Reports/TopSelling', [
            'topProducts' => $topProducts,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }
}