<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SettingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use Carbon\Carbon;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ==========================================
    // 1. BISA DIAKSES SEMUA ROLE
    // Owner, Manager, Cashier
    // ==========================================

    Route::get('/dashboard', function () {

        $today = Carbon::today();

        // ==========================================
        // PENJUALAN HARI INI
        // Sumber data:
        // sales + sale_details
        // ==========================================

        $salesToday = Sale::with('saleDetails')
            ->whereDate('created_at', $today)
            ->get();

        // Total omzet hari ini
        $revenueToday = $salesToday->sum('grand_total');

        // Jumlah transaksi hari ini
        $transactionsToday = $salesToday->count();

        // ==========================================
        // LABA HARI INI
        // Laba = (harga jual - harga beli) x quantity
        // ==========================================

        $profitToday = $salesToday->sum(function ($sale) {

            return $sale->saleDetails->sum(function ($detail) {

                return (
                    (float) $detail->selling_price
                    - (float) $detail->purchase_price
                ) * (float) $detail->quantity;

            });

        });

        // ==========================================
        // PRODUK TERLARIS HARI INI
        //
        // Menggunakan sale_details.quantity
        // dan sale_details.subtotal
        //
        // Jadi sumber datanya sama dengan transaksi POS.
        // ==========================================

        $topSellingProducts = SaleDetail::query()
            ->join(
                'sales',
                'sales.id',
                '=',
                'sale_details.sale_id'
            )
            ->join(
                'products',
                'products.id',
                '=',
                'sale_details.product_id'
            )
            ->whereDate('sales.created_at', $today)
            ->select(
                'products.id',
                'products.name',
                'products.unit',
                DB::raw('SUM(sale_details.quantity) as total_qty'),
                DB::raw('SUM(sale_details.subtotal) as total_revenue')
            )
            ->groupBy(
                'products.id',
                'products.name',
                'products.unit'
            )
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($product) {

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'unit' => $product->unit,
                    'total_qty' => (float) $product->total_qty,
                    'total_revenue' => (float) $product->total_revenue,
                ];

            });

        // ==========================================
        // STOK MENIPIS
        //
        // Mengambil stock aktual dari products.stock
        // ==========================================

        $lowStockProducts = Product::where('stock', '<=', 10)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get();

        // ==========================================
        // GRAFIK OMZET 7 HARI TERAKHIR
        // ==========================================

        $chartData = [
            'labels' => [],
            'data' => [],
        ];

        for ($i = 6; $i >= 0; $i--) {

            $date = Carbon::today()->subDays($i);

            $total = Sale::whereDate(
                'created_at',
                $date
            )->sum('grand_total');

            $chartData['labels'][] = $date->format('d M');

            $chartData['data'][] = (float) $total;
        }

        // ==========================================
        // KIRIM DATA KE DASHBOARD
        // ==========================================

        return Inertia::render('Dashboard', [

            'summary' => [

                'revenue_today' => (float) $revenueToday,

                'profit_today' => (float) $profitToday,

                'transactions_today' => $transactionsToday,

                'total_products' => Product::count(),

                'total_customers' => Customer::count(),

                'total_suppliers' => Supplier::count(),

            ],

            'top_selling_products' => $topSellingProducts,

            'low_stock_products' => $lowStockProducts,

            'chart_data' => $chartData,

        ]);

    })->name('dashboard');


    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');


    // ==========================================
    // TRANSAKSI KASIR
    // ==========================================

    Route::get('/pos', [PosController::class, 'index'])
        ->name('pos.index');

    Route::post('/pos', [PosController::class, 'store'])
        ->name('pos.store');

    Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])
        ->name('pos.receipt');

    Route::get('/pos/history', [PosController::class, 'history'])
        ->name('pos.history');


    // ==========================================
    // MASTER DATA - READ ONLY
    // ==========================================

    Route::get(
        'products',
        [ProductController::class, 'index']
    )->name('products.index');

    Route::get(
        'categories',
        [CategoryController::class, 'index']
    )->name('categories.index');

    Route::get(
        'customers',
        [CustomerController::class, 'index']
    )->name('customers.index');


    // ==========================================
    // 2. HANYA OWNER & MANAGER
    // ==========================================

    Route::middleware('role:owner,manager')->group(function () {

        // Products
        Route::resource('products', ProductController::class)
            ->except(['index']);

        // Categories
        Route::resource('categories', CategoryController::class)
            ->except(['index']);

        // Customers
        Route::resource('customers', CustomerController::class)
            ->except(['index']);

        // Suppliers
        Route::resource('suppliers', SupplierController::class);

        // Stock Adjustment
        Route::resource(
            'stock-adjustments',
            StockAdjustmentController::class
        )->only([
            'index',
            'create',
            'store'
        ]);

        // Purchases
        Route::resource(
            'purchases',
            PurchaseController::class
        )->only([
            'index',
            'create',
            'store'
        ]);

    });


    // ==========================================
    // 3. HANYA OWNER
    // ==========================================

    Route::middleware('role:owner')->group(function () {

        Route::get(
            '/reports/sales',
            [ReportController::class, 'sales']
        )->name('reports.sales');

        Route::get(
            '/reports/stock-card',
            [ReportController::class, 'stockCard']
        )->name('reports.stock_card');

        Route::get(
            '/reports/top-selling',
            [ReportController::class, 'topSelling']
        )->name('reports.top_selling');

        Route::resource('users', UserController::class);

        Route::get(
            '/settings',
            [SettingController::class, 'index']
        )->name('settings.index');

        Route::post(
            '/settings',
            [SettingController::class, 'update']
        )->name('settings.update');

    });

});

require __DIR__.'/auth.php';