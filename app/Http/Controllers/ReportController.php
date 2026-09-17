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
        $products = \App\Models\Product::orderBy('name')->get();
        
        // Tangkap parameter filter & fitur
        $selectedProductId = $request->input('product_id');
        $startDate = $request->input('start_date', \Carbon\Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', \Carbon\Carbon::now()->endOfMonth()->format('Y-m-d'));
        $search = $request->input('search');
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc');

        $movements = collect();
        $selectedProduct = null;

        if ($selectedProductId) {
            $selectedProduct = \App\Models\Product::find($selectedProductId);

            // 1. Ambil Penjualan (Keluar)
            $sales = \Illuminate\Support\Facades\DB::table('sale_details')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sale_details.product_id', $selectedProductId)
                ->whereBetween('sales.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select('sales.created_at as date', 'sales.invoice_no as reference', 'sale_details.quantity as out')
                ->get()->map(function($item) {
                    return [
                        'date' => $item->date,
                        'type' => 'Penjualan (Kasir)',
                        'reference' => $item->reference,
                        'in' => 0,
                        'out' => (float) $item->out,
                        'description' => 'Terjual ke pelanggan'
                    ];
                });

            // 2. Ambil Pembelian (Masuk)
            $purchases = \Illuminate\Support\Facades\DB::table('purchase_details')
                ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.id')
                ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
                ->where('purchase_details.product_id', $selectedProductId)
                ->whereBetween('purchases.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select('purchases.created_at as date', 'purchases.invoice_no as reference', 'purchase_details.quantity as in', 'suppliers.name as supplier')
                ->get()->map(function($item) {
                    return [
                        'date' => $item->date,
                        'type' => 'Pembelian (Restock)',
                        'reference' => $item->reference,
                        'in' => (float) $item->in,
                        'out' => 0,
                        'description' => 'Barang masuk dari ' . ($item->supplier ?? 'Supplier')
                    ];
                });

            // 3. Ambil Stok Opname
            $adjustments = \Illuminate\Support\Facades\DB::table('stock_adjustments')
                ->where('product_id', $selectedProductId)
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
                ->select('created_at as date', 'id', 'adjustment', 'reason')
                ->get()->map(function($item) {
                    return [
                        'date' => $item->date,
                        'type' => 'Stok Opname',
                        'reference' => 'ADJ-' . $item->id,
                        'in' => $item->adjustment > 0 ? (float) $item->adjustment : 0,
                        'out' => $item->adjustment < 0 ? abs((float) $item->adjustment) : 0,
                        'description' => $item->reason
                    ];
                });

            // Gabungkan semua data
            $movements = $sales->concat($purchases)->concat($adjustments);

            // Terapkan PENCARIAN (Search) manual pada Collection
            if ($search) {
                $searchLower = strtolower($search);
                $movements = $movements->filter(function ($item) use ($searchLower) {
                    return str_contains(strtolower($item['reference']), $searchLower) ||
                           str_contains(strtolower($item['type']), $searchLower) ||
                           str_contains(strtolower($item['description']), $searchLower);
                });
            }

            // Terapkan PENGURUTAN (Sort) manual pada Collection
            $isDesc = $direction === 'desc';
            $movements = $movements->sortBy($sort, SORT_REGULAR, $isDesc)->values();

            // Format tanggal setelah diurutkan
            $movements = $movements->map(function($m) {
                $m['date_formatted'] = \Carbon\Carbon::parse($m['date'])->format('d/m/Y H:i');
                return $m;
            });
        }

        // Terapkan PAGINATION manual pada Collection
        $page = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $paginatedMovements = new \Illuminate\Pagination\LengthAwarePaginator(
            $movements->forPage($page, $perPage)->values(),
            $movements->count(),
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return \Inertia\Inertia::render('Reports/StockCard', [
            'products' => $products,
            'movements' => $paginatedMovements,
            'selected_product' => $selectedProduct,
            'filters' => [
                'product_id' => $selectedProductId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
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