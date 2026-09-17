<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\StockAdjustment;
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
        $products = Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get([
                'id',
                'barcode',
                'name',
                'unit',
                'stock',
            ]);

        $selectedProduct = null;
        $movements = collect();

        $productId = $request->integer('product_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if ($productId && $startDate && $endDate) {
            $selectedProduct = Product::find($productId);

            if ($selectedProduct) {
                /*
                * ==========================================================
                * 1. AMBIL TRANSAKSI DALAM PERIODE
                * ==========================================================
                */

                // PENJUALAN / POS
                $sales = DB::table('sale_details')
                    ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                    ->where('sale_details.product_id', $productId)
                    ->whereBetween('sales.created_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ])
                    ->orderBy('sales.created_at')
                    ->get([
                        'sales.id',
                        'sales.invoice_no',
                        'sales.created_at',
                        'sale_details.quantity',
                    ]);

                // PEMBELIAN / PURCHASE
                $purchases = DB::table('purchase_details')
                    ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
                    ->where('purchase_details.product_id', $productId)
                    ->whereBetween('purchases.created_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ])
                    ->orderBy('purchases.created_at')
                    ->get([
                        'purchases.id',
                        'purchases.invoice_no',
                        'purchases.created_at',
                        'purchase_details.quantity',
                    ]);

                // STOCK ADJUSTMENT
                $adjustments = StockAdjustment::query()
                    ->where('product_id', $productId)
                    ->whereBetween('created_at', [
                        $startDate . ' 00:00:00',
                        $endDate . ' 23:59:59',
                    ])
                    ->orderBy('created_at')
                    ->get([
                        'id',
                        'created_at',
                        'adjustment',
                        'reason',
                    ]);

                /*
                * ==========================================================
                * 2. HITUNG SALDO HISTORIS
                *
                * Karena products.stock adalah stok SEKARANG,
                * kita perlu mundur dari stok sekarang untuk mendapatkan
                * stok pada akhir periode yang dipilih.
                * ==========================================================
                */

                $currentStock = (float) $selectedProduct->stock;

                // Transaksi setelah end_date
                $salesAfterEnd = (float) DB::table('sale_details')
                    ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                    ->where('sale_details.product_id', $productId)
                    ->where('sales.created_at', '>', $endDate . ' 23:59:59')
                    ->sum('sale_details.quantity');

                $purchasesAfterEnd = (float) DB::table('purchase_details')
                    ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
                    ->where('purchase_details.product_id', $productId)
                    ->where('purchases.created_at', '>', $endDate . ' 23:59:59')
                    ->sum('purchase_details.quantity');

                $adjustmentsAfterEnd = (float) StockAdjustment::query()
                    ->where('product_id', $productId)
                    ->where('created_at', '>', $endDate . ' 23:59:59')
                    ->sum('adjustment');

                /*
                * Stok akhir periode =
                * stok sekarang
                * + penjualan setelah periode
                * - pembelian setelah periode
                * - adjustment setelah periode
                */
                $stockAtEnd = $currentStock
                    + $salesAfterEnd
                    - $purchasesAfterEnd
                    - $adjustmentsAfterEnd;

                /*
                * ==========================================================
                * 3. HITUNG SALDO AWAL
                * ==========================================================
                */

                $salesBeforeStart = (float) DB::table('sale_details')
                    ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                    ->where('sale_details.product_id', $productId)
                    ->where('sales.created_at', '<', $startDate . ' 00:00:00')
                    ->sum('sale_details.quantity');

                $purchasesBeforeStart = (float) DB::table('purchase_details')
                    ->join('purchases', 'purchases.id', '=', 'purchase_details.purchase_id')
                    ->where('purchase_details.product_id', $productId)
                    ->where('purchases.created_at', '<', $startDate . ' 00:00:00')
                    ->sum('purchase_details.quantity');

                $adjustmentsBeforeStart = (float) StockAdjustment::query()
                    ->where('product_id', $productId)
                    ->where('created_at', '<', $startDate . ' 00:00:00')
                    ->sum('adjustment');

                /*
                * Saldo awal =
                * saldo akhir
                * - pembelian periode
                * + penjualan periode
                * - adjustment periode
                *
                * Dengan pendekatan ini, saldo awal benar-benar berasal
                * dari histori transaksi.
                */
                $totalPeriodPurchases = (float) $purchases->sum('quantity');
                $totalPeriodSales = (float) $sales->sum('quantity');
                $totalPeriodAdjustments = (float) $adjustments->sum('adjustment');

                $openingStock = $stockAtEnd
                    - $totalPeriodPurchases
                    + $totalPeriodSales
                    - $totalPeriodAdjustments;

                /*
                * ==========================================================
                * 4. BENTUK MOVEMENT
                * ==========================================================
                */

                foreach ($purchases as $purchase) {
                    $movements->push([
                        'id' => 'purchase-' . $purchase->id,
                        'date' => $purchase->created_at,
                        'type' => 'Pembelian',
                        'reference' => $purchase->invoice_no ?? '-',
                        'description' => 'Pembelian / Restock',
                        'in' => round((float) $purchase->quantity, 3),
                        'out' => 0,
                        'sort_priority' => 1,
                    ]);
                }

                foreach ($sales as $sale) {
                    $movements->push([
                        'id' => 'sale-' . $sale->id,
                        'date' => $sale->created_at,
                        'type' => 'Penjualan',
                        'reference' => $sale->invoice_no ?? '-',
                        'description' => 'Terjual ke pelanggan',
                        'in' => 0,
                        'out' => round((float) $sale->quantity, 3),
                        'sort_priority' => 2,
                    ]);
                }

                foreach ($adjustments as $adjustment) {
                    $adjustmentValue = round((float) $adjustment->adjustment, 3);

                    $movements->push([
                        'id' => 'adjustment-' . $adjustment->id,
                        'date' => $adjustment->created_at,
                        'type' => 'Penyesuaian',
                        'reference' => 'ADJ-' . $adjustment->id,
                        'description' => $adjustment->reason,
                        'in' => $adjustmentValue > 0 ? $adjustmentValue : 0,
                        'out' => $adjustmentValue < 0 ? abs($adjustmentValue) : 0,
                        'sort_priority' => 3,
                    ]);
                }

                /*
                * ==========================================================
                * 5. URUTKAN SECARA KRONOLOGIS
                *
                * Ini penting karena saldo berjalan harus dihitung
                * berdasarkan urutan waktu, bukan urutan tampilan.
                * ==========================================================
                */

                $chronologicalMovements = $movements
                    ->sort(function ($a, $b) {
                        $dateCompare = strtotime($a['date']) <=> strtotime($b['date']);

                        if ($dateCompare !== 0) {
                            return $dateCompare;
                        }

                        return $a['sort_priority'] <=> $b['sort_priority'];
                    })
                    ->values();

                /*
                * ==========================================================
                * 6. HITUNG SALDO BERJALAN
                * ==========================================================
                */

                $runningBalance = $openingStock;

                $chronologicalMovements = $chronologicalMovements
                    ->map(function ($movement) use (&$runningBalance) {
                        $runningBalance += $movement['in'];
                        $runningBalance -= $movement['out'];

                        $movement['balance'] = round($runningBalance, 3);

                        return $movement;
                    });

                /*
                * ==========================================================
                * 7. TOTAL
                * ==========================================================
                */

                $totalIn = round(
                    $chronologicalMovements->sum('in'),
                    3
                );

                $totalOut = round(
                    $chronologicalMovements->sum('out'),
                    3
                );

                $closingStock = round(
                    $openingStock + $totalIn - $totalOut,
                    3
                );

                /*
                * ==========================================================
                * 8. DATA UNTUK FRONTEND
                * ==========================================================
                */

                $movements = $chronologicalMovements
                    ->map(function ($movement) {
                        unset($movement['sort_priority']);

                        return $movement;
                    })
                    ->values();

                $selectedProduct = [
                    'id' => $selectedProduct->id,
                    'barcode' => $selectedProduct->barcode,
                    'name' => $selectedProduct->name,
                    'unit' => $selectedProduct->unit,
                    'stock' => round((float) $selectedProduct->stock, 3),

                    // Stock historis
                    'opening_stock' => round($openingStock, 3),
                    'total_in' => $totalIn,
                    'total_out' => $totalOut,
                    'closing_stock' => $closingStock,
                ];
            }
        }

        return Inertia::render('Reports/StockCard', [
            'products' => $products,
            'movements' => $movements,
            'selected_product' => $selectedProduct,
            'filters' => [
                'product_id' => $productId,
                'start_date' => $startDate,
                'end_date' => $endDate,
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