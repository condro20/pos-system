<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\StockAdjustment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Inisialisasi Faker dengan data lokalisasi Indonesia
        $faker = Faker::create('id_ID');

        // ==========================================
        // 1. BUAT DATA ROLE & USER
        // ==========================================
        $roleOwner = Role::create(['name' => 'owner']);
        $roleManager = Role::create(['name' => 'manager']);
        $roleCashier = Role::create(['name' => 'cashier']);

        $owner = User::create([
            'name' => 'Admin Owner',
            'email' => 'owner@toko.com',
            'password' => Hash::make('password'),
            'role_id' => $roleOwner->id,
        ]);

        $cashier = User::create([
            'name' => 'Siti Kasir',
            'email' => 'kasir@toko.com',
            'password' => Hash::make('password'),
            'role_id' => $roleCashier->id,
        ]);

        $manager = User::create([
            'name' => 'Herman Manajer',
            'email' => 'manajer@toko.com',
            'password' => Hash::make('password'),
            'role_id' => $roleManager->id,
        ]);


        // ==========================================
        // 2. BUAT DATA KATEGORI
        // ==========================================
        $kategoriList = ['Beras', 'Minyak Goreng', 'Telur & Unggas', 'Bumbu Dapur', 'Minuman', 'Kopi & Teh', 'Mie Instan', 'Sabun & Deterjen', 'Susu', 'Tepung & Gandum'];
        $kategoriIds = [];
        
        foreach ($kategoriList as $kat) {
            $kategoriIds[] = Category::create(['name' => $kat])->id;
        }


        // ==========================================
        // 3. BUAT 100 SUPPLIER & 100 PELANGGAN
        // ==========================================
        $supplierIds = [];
        for ($i = 0; $i < 100; $i++) {
            $supplierIds[] = Supplier::create([
                'name' => 'PT ' . $faker->company,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
            ])->id;
        }

        $customerIds = [];
        for ($i = 0; $i < 100; $i++) {
            $customerIds[] = Customer::create([
                'name' => $faker->name,
                'phone' => $faker->phoneNumber,
                'address' => $faker->address,
            ])->id;
        }


        // ==========================================
        // 4. BUAT 100 PRODUK
        // ==========================================
        $productIds = [];
        $units = ['kg', 'liter', 'pcs', 'bal', 'karton', 'gram'];
        
        // Produk khusus
        Product::create([
            'category_id' => Category::where('name', 'Telur & Unggas')->first()->id,
            'barcode' => 'CJ28-001',
            'name' => 'Telur Ayam Ras CJ 28 Farm',
            'unit' => 'kg',
            'stock' => 150.5,
            'purchase_price' => 24000,
            'selling_price' => 26500,
        ]);

        for ($i = 1; $i <= 100; $i++) {
            // HPP kelipatan 500 (misal: 2500, 3000, 15000)
            $hpp = $faker->numberBetween(5, 100) * 500; 
            // Margin untung kelipatan 500
            $hargaJual = $hpp + ($faker->numberBetween(1, 10) * 500); 

            $product = Product::create([
                'category_id' => $faker->randomElement($kategoriIds),
                'barcode' => 'BRG-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'name' => ucfirst($faker->word) . ' ' . ucfirst($faker->word) . ' ' . $faker->companySuffix,
                'unit' => $faker->randomElement($units),
                'stock' => $faker->randomFloat(2, 5, 200), // Stok saat ini
                'purchase_price' => $hpp,
                'selling_price' => $hargaJual,
            ]);
            $productIds[] = $product->id;
        }


        // ==========================================
        // 5. TRANSAKSI (7 HARI TERAKHIR)
        // ==========================================
        
        // A. 100 TRANSAKSI PEMBELIAN (RESTOCK)
        $adminUsers = [$owner->id, $manager->id];

        for ($i = 1; $i <= 1000; $i++) {
            // Acak tanggal 0-7 hari ke belakang
            $date = Carbon::today()->subDays(rand(0, 7))->addHours(rand(7, 16))->addMinutes(rand(0, 59));
            
            $purchase = Purchase::create([
                'invoice_no' => 'PO-' . $date->format('Ymd') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'user_id' => $faker->randomElement($adminUsers),
                'supplier_id' => $faker->randomElement($supplierIds),
                'grand_total' => 0,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $totalPurchase = 0;
            $itemsCount = rand(1, 5); // 1-5 macam barang per nota
            for ($j = 0; $j < $itemsCount; $j++) {
                $prod = Product::find($faker->randomElement($productIds));
                $qty = $faker->numberBetween(5, 50);
                $subtotal = $qty * $prod->purchase_price;
                $totalPurchase += $subtotal;

                PurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $prod->id,
                    'quantity' => $qty,
                    'price' => $prod->purchase_price,
                    'subtotal' => $subtotal,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
            $purchase->update(['grand_total' => $totalPurchase]);
        }

        // B. 150 TRANSAKSI PENJUALAN (KASIR)
        $cashierUsers = [$owner->id, $cashier->id]; // Yang nge-kasir bisa Owner atau Kasir
        
        for ($i = 1; $i <= 1500; $i++) {
            $date = Carbon::today()->subDays(rand(0, 7))->addHours(rand(7, 21))->addMinutes(rand(0, 59));
            
            $sale = Sale::create([
                'invoice_no' => 'INV-' . $date->format('Ymd') . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'user_id' => $faker->randomElement($cashierUsers),
                'customer_id' => $faker->boolean(40) ? $faker->randomElement($customerIds) : null, // 40% pelanggan terdaftar
                'subtotal' => 0,
                'discount' => 0,
                'grand_total' => 0,
                'payment_method' => $faker->randomElement(['Tunai', 'Tunai', 'Transfer']),
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $totalSale = 0;
            $itemsCount = rand(1, 6); // 1-6 barang dibeli pelanggan
            for ($j = 0; $j < $itemsCount; $j++) {
                $prod = Product::find($faker->randomElement($productIds));
                $qty = $faker->randomFloat(1, 1, 10); // Bisa beli pecahan misal 1.5 kg
                $subtotal = $qty * $prod->selling_price;
                $totalSale += $subtotal;

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $prod->id,
                    'quantity' => $qty,
                    'purchase_price' => $prod->purchase_price, // Penting untuk hitung laba
                    'selling_price' => $prod->selling_price,
                    'subtotal' => $subtotal,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }
            $sale->update(['subtotal' => $totalSale, 'grand_total' => $totalSale]);
        }

        // C. 50 STOK OPNAME (PENYESUAIAN)
        for ($i = 1; $i <= 50; $i++) {
            $date = Carbon::today()->subDays(rand(0, 7))->addHours(rand(8, 20))->addMinutes(rand(0, 59));
            
            // Ambil produk acak untuk disesuaikan
            $productId = $faker->randomElement($productIds);
            $product = Product::find($productId);
            
            // Hitung logika stok sistem dan fisik
            $systemStock = $product->stock; // Stok di sistem
            $adjustment = $faker->randomElement([-1, -0.5, -2, -2.5, -5, -3]);
            $physicalStock = $systemStock + $adjustment; // Stok fisik asli
            
            StockAdjustment::create([
                'product_id' => $productId,
                'user_id' => $owner->id,
                'system_stock' => $systemStock, // <--- TAMBAHKAN INI
                'physical_stock' => $physicalStock, // <--- TAMBAHKAN INI
                'adjustment' => $adjustment,
                'reason' => $faker->randomElement(['Barang tumpah di gudang', 'Kemasan sobek', 'Dimakan tikus', 'Salah hitung dari supplier', 'Barang rusak', 'Barang ketemu di etalase']),
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }
    }
}