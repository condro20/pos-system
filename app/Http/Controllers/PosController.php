<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    public function index()
    {
        $products = Product::where('stock', '>', 0)->get();
        return Inertia::render('POS/Index', ['products' => $products]);
    }

    public function store(Request $request)
    {
        // 1. Validasi input dari Vue
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
        ]);

        try {
            // Memulai Transaksi Database (Mencegah data setengah jadi jika terjadi error)
            DB::beginTransaction();

            // 2. Generate Nomor Invoice (Contoh: INV-20260915-0001)
            $datePrefix = date('Ymd');
            $lastSale = Sale::where('invoice_no', 'like', "INV-{$datePrefix}-%")
                            ->orderBy('id', 'desc')->first();
            $sequence = $lastSale ? (int) substr($lastSale->invoice_no, -4) + 1 : 1;
            $invoiceNo = 'INV-' . $datePrefix . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $grandTotal = 0;
            $saleDetails = [];

            // 3. Proses Item dan Kurangi Stok (Perhitungan ulang harga di server agar aman)
            foreach ($request->items as $item) {
                // lockForUpdate() mengunci baris produk ini sampai transaksi selesai
                // Mencegah *race condition* jika kasir lain menjual produk yang sama
                $product = Product::where('id', $item['product_id'])->lockForUpdate()->first();
                
                $qty = $item['qty'];
                $subtotal = $qty * $product->selling_price;
                $grandTotal += $subtotal;

                // Siapkan data detail untuk di-insert (Merekam HPP / Purchase Price historis)
                $saleDetails[] = [
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'purchase_price' => $product->purchase_price, 
                    'selling_price' => $product->selling_price,
                    'subtotal' => $subtotal,
                ];

                // Kurangi stok lalu simpan
                $product->stock -= $qty;
                $product->save();
            }

            // 4. Buat Header Transaksi
            $sale = Sale::create([
                'invoice_no' => $invoiceNo,
                'user_id' => Auth::id(),
                'customer_id' => null, // Pelanggan umum
                'subtotal' => $grandTotal,
                'discount' => 0,
                'grand_total' => $grandTotal,
                'payment_method' => $request->payment_method,
            ]);

            // 5. Simpan Data Detail Transaksi
            $sale->saleDetails()->createMany($saleDetails);

            // Jika semua blok kode di atas berhasil, Commit (simpan permanen) ke database
            DB::commit();

            // Mengembalikan response JSON yang berisi URL untuk cetak struk
            return response()->json([
                'success' => true,
                'print_url' => route('pos.receipt', $sale->id)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            // Ubah juga error response-nya
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function receipt(Sale $sale)
    {
        // Load relasi agar data detail dan kasir bisa ditampilkan di struk
        $sale->load(['saleDetails.product', 'user']);
        
        // Memanggil file blade receipt.blade.php
        return view('receipt', compact('sale'));
    }

    public function history(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'created_at'); // Default urut berdasarkan waktu
        $direction = $request->input('direction', 'desc'); // Default yang terbaru di atas

        // Gunakan leftJoin agar bisa melakukan pencarian dan pengurutan berdasarkan nama Kasir (User)
        $query = \App\Models\Sale::with(['user', 'saleDetails.product'])
            ->select('sales.*') // Wajib agar ID tidak bentrok
            ->leftJoin('users', 'sales.user_id', '=', 'users.id')
            ->when($search, function ($q, $search) {
                $q->where('sales.invoice_no', 'ilike', "%{$search}%")
                  ->orWhere('users.name', 'ilike', "%{$search}%");
            });

        // Logika Sorting Khusus
        if ($sort === 'cashier') {
            $query->orderBy('users.name', $direction);
        } else {
            $query->orderBy('sales.' . $sort, $direction);
        }

        $sales = $query->paginate(15)->withQueryString();

        return \Inertia\Inertia::render('POS/History', [
            'sales' => $sales,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }
}