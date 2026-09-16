<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at'); // Default urut berdasarkan waktu terbaru
        $direction = $request->input('direction', 'desc');

        // Query data dengan Eager Loading dan Join untuk fitur Search & Sort
        $query = StockAdjustment::with(['product', 'user'])
            ->select('stock_adjustments.*') // Hindari bentrok ID
            ->leftJoin('products', 'stock_adjustments.product_id', '=', 'products.id')
            ->leftJoin('users', 'stock_adjustments.user_id', '=', 'users.id')
            ->when($search, function ($q, $search) {
                $q->where('products.name', 'ilike', "%{$search}%")
                  ->orWhere('users.name', 'ilike', "%{$search}%")
                  ->orWhere('stock_adjustments.reason', 'ilike', "%{$search}%");
            });

        // Logika Sorting Khusus
        if ($sort === 'product') {
            $query->orderBy('products.name', $direction);
        } elseif ($sort === 'user') {
            $query->orderBy('users.name', $direction);
        } else {
            $query->orderBy('stock_adjustments.' . $sort, $direction);
        }

        $stockAdjustments = $query->paginate(10)->withQueryString();

        return Inertia::render('StockAdjustments/Index', [
            'stockAdjustments' => $stockAdjustments,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('StockAdjustments/Create', [
            'products' => Product::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'physical_stock' => 'required|numeric|min:0',
            'reason' => 'required|string|max:255',
        ]);

        try {
            DB::beginTransaction();

            // Lock baris produk agar tidak ada transaksi kasir yang bentrok saat opname
            $product = Product::where('id', $request->product_id)->lockForUpdate()->first();

            $systemStock = $product->stock;
            $physicalStock = $request->physical_stock;
            $adjustment = $physicalStock - $systemStock;

            // 1. Catat riwayat
            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => Auth::id(),
                'system_stock' => $systemStock,
                'physical_stock' => $physicalStock,
                'adjustment' => $adjustment,
                'reason' => $request->reason,
            ]);

            // 2. Update stok di master data
            $product->stock = $physicalStock;
            $product->save();

            DB::commit();

            return redirect()->route('stock-adjustments.index')->with('success', 'Stok berhasil disesuaikan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal menyimpan penyesuaian stok.']);
        }
    }
}