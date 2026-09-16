<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at'); // Default terbaru di atas
        $direction = $request->input('direction', 'desc');

        // Query dengan Eager Loading (Modal) dan leftJoin (Search/Sort)
        $query = Purchase::with(['supplier', 'purchaseDetails.product'])
            ->select('purchases.*') // Hindari bentrok ID
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->when($search, function ($q, $search) {
                $q->where('purchases.invoice_no', 'ilike', "%{$search}%")
                  ->orWhere('suppliers.name', 'ilike', "%{$search}%");
            });

        // Logika Sorting
        if ($sort === 'supplier') {
            $query->orderBy('suppliers.name', $direction);
        } else {
            $query->orderBy('purchases.' . $sort, $direction);
        }

        $purchases = $query->paginate(10)->withQueryString();

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Purchases/Create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Generate Nomor PO (Purchase Order)
            $datePrefix = date('Ymd');
            $lastPurchase = Purchase::where('invoice_no', 'like', "PO-{$datePrefix}-%")
                                    ->orderBy('id', 'desc')->first();
            $sequence = $lastPurchase ? (int) substr($lastPurchase->invoice_no, -4) + 1 : 1;
            $invoiceNo = 'PO-' . $datePrefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $grandTotal = 0;
            $purchaseDetails = [];

            foreach ($request->items as $item) {
                $subtotal = $item['qty'] * $item['price'];
                $grandTotal += $subtotal;

                $purchaseDetails[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['qty'],
                    'price' => $item['price'],
                    'subtotal' => $subtotal,
                ];

                // Kunci baris produk, tambah stok, dan perbarui Harga Beli (HPP)
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();
                $product->stock += $item['qty'];
                $product->purchase_price = $item['price']; // HPP di-update dengan harga kulakan terbaru
                $product->save();
            }

            $purchase = Purchase::create([
                'invoice_no' => $invoiceNo,
                'user_id' => Auth::id(),
                'supplier_id' => $request->supplier_id,
                'grand_total' => $grandTotal,
            ]);

            $purchase->purchaseDetails()->createMany($purchaseDetails);

            DB::commit();

            return redirect()->route('purchases.index')->with('success', 'Barang masuk berhasil dicatat dan stok bertambah!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Gagal memproses pembelian: ' . $e->getMessage()]);
        }
    }
}