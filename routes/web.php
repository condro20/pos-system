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
use App\Models\Sale;
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
    // 1. BISA DIAKSES SEMUA ROLE (Owner, Manager, Cashier)
    // ==========================================
    Route::get('/dashboard', function () {
        $today = Carbon::today();

        $salesToday = Sale::with('saleDetails')->whereDate('created_at', $today)->get();
        $revenueToday = $salesToday->sum('grand_total');
        $transactionsToday = $salesToday->count();

        $profitToday = $salesToday->sum(function ($sale) {
            return $sale->saleDetails->sum(function ($detail) {
                return ($detail->selling_price - $detail->purchase_price) * $detail->quantity;
            });
        });

        $lowStockProducts = Product::where('stock', '<=', 10)->orderBy('stock', 'asc')->take(5)->get();

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $total = Sale::whereDate('created_at', $date)->sum('grand_total');
            $chartData['labels'][] = $date->format('d M'); // Contoh: 15 Sep
            $chartData['data'][] = $total;
        }

        return Inertia::render('Dashboard', [
            'summary' => [
                'revenue_today' => $revenueToday,
                'profit_today' => $profitToday,
                'transactions_today' => $transactionsToday,
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                'total_suppliers' => Supplier::count(),
            ],
            'low_stock_products' => $lowStockProducts,
            'chart_data' => $chartData,
        ]);
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Transaksi Kasir
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos', [PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/receipt/{sale}', [PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('/pos/history', [PosController::class, 'history'])->name('pos.history');

    // Akses Read-Only (Hanya melihat daftar) untuk Master Data
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');


    // ==========================================
    // 2. HANYA OWNER & MANAGER
    // ==========================================
    Route::middleware('role:owner,manager')->group(function () {
        // Akses penuh (Create, Edit, Delete) Master Data selain index
        Route::resource('products', ProductController::class)->except(['index']);
        Route::resource('categories', CategoryController::class)->except(['index']);
        Route::resource('customers', CustomerController::class)->except(['index']);
        
        // Akses penuh modul lainnya
        Route::resource('suppliers', SupplierController::class);
        Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'create', 'store']);
        Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store']);
    });


    // ==========================================
    // 3. HANYA OWNER
    // ==========================================
    Route::middleware('role:owner')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reports/stock-card', [ReportController::class, 'stockCard'])->name('reports.stock_card');
        Route::get('/reports/top-selling', [ReportController::class, 'topSelling'])->name('reports.top_selling');
        Route::resource('users', UserController::class);
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

});

require __DIR__.'/auth.php';