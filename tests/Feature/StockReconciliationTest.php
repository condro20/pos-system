<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private function createManager(): User
    {
        $role = Role::create([
            'name' => 'manager',
        ]);

        $user = User::factory()->create();

        $user->role_id = $role->id;
        $user->save();

        return $user->fresh('role');
    }

    private function createProduct(float $stock = 10): Product
    {
        $category = Category::create([
            'name' => 'Test Category',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'barcode' => 'TEST-' . uniqid(),
            'name' => 'Produk Test',
            'unit' => 'pcs',
            'stock' => $stock,
            'purchase_price' => 5000,
            'selling_price' => 7500,
        ]);
    }

    public function test_stock_reconciliation_purchase_sale_and_adjustment(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $supplier = Supplier::create([
            'name' => 'Supplier Test',
            'phone' => '08123456789',
            'address' => 'Alamat Test',
        ]);

        /*
        |--------------------------------------------------------------------------
        | 1. PURCHASE +5
        |--------------------------------------------------------------------------
        */

        $purchaseResponse = $this
            ->actingAs($user)
            ->post(
                route('purchases.store'),
                [
                    'supplier_id' => $supplier->id,

                    'items' => [
                        [
                            'product_id' => $product->id,
                            'qty' => 5,
                            'price' => 5000,
                        ],
                    ],
                ]
            );

        $purchaseResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('purchases.index')
            );

        $product->refresh();

        $this->assertEquals(
            15.000,
            (float) $product->stock
        );

        /*
        |--------------------------------------------------------------------------
        | 2. SALE -2.5
        |--------------------------------------------------------------------------
        */

        $saleResponse = $this
            ->actingAs($user)
            ->postJson(
                route('pos.store'),
                [
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'qty' => 2.5,
                        ],
                    ],

                    'payment_method' => 'Cash',
                ]
            );

        $saleResponse
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $product->refresh();

        $this->assertEquals(
            12.500,
            (float) $product->stock
        );

        /*
        |--------------------------------------------------------------------------
        | 3. ADJUSTMENT
        |
        | system = 12.5
        | physical = 11.75
        | adjustment = -0.75
        |--------------------------------------------------------------------------
        */

        $adjustmentResponse = $this
            ->actingAs($user)
            ->post(
                route('stock-adjustments.store'),
                [
                    'product_id' => $product->id,
                    'physical_stock' => 11.75,
                    'reason' => 'Barang rusak',
                ]
            );

        $adjustmentResponse
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('stock-adjustments.index')
            );

        $product->refresh();

        $this->assertEquals(
            11.750,
            (float) $product->stock
        );

        /*
        |--------------------------------------------------------------------------
        | 4. REKONSILIASI
        |--------------------------------------------------------------------------
        */

        $totalPurchase = (float) PurchaseDetail::query()
            ->where('product_id', $product->id)
            ->sum('quantity');

        $totalSale = (float) SaleDetail::query()
            ->where('product_id', $product->id)
            ->sum('quantity');

        $totalAdjustment = (float) StockAdjustment::query()
            ->where('product_id', $product->id)
            ->sum('adjustment');

        /*
        |--------------------------------------------------------------------------
        | Rumus:
        |
        | stok akhir =
        | stok awal
        | + purchase
        | - sale
        | + adjustment
        |--------------------------------------------------------------------------
        */

        $calculatedStock =
            10
            + $totalPurchase
            - $totalSale
            + $totalAdjustment;

        $this->assertEquals(
            11.750,
            round($calculatedStock, 3)
        );

        $this->assertEquals(
            round($calculatedStock, 3),
            round((float) $product->stock, 3)
        );
    }

    public function test_product_update_cannot_change_stock(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(25);

        $response = $this
            ->actingAs($user)
            ->put(
                route('products.update', $product->id),
                [
                    'category_id' => $product->category_id,
                    'barcode' => $product->barcode,
                    'name' => 'Produk Diubah',
                    'unit' => 'pcs',

                    /*
                    |------------------------------------------------------------------
                    | Percobaan manipulasi stok
                    |------------------------------------------------------------------
                    */
                    'stock' => 999,

                    'purchase_price' => 6000,
                    'selling_price' => 9000,
                ]
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(
                route('products.index')
            );

        $product->refresh();

        /*
        |--------------------------------------------------------------------------
        | Stock HARUS tetap 25
        |--------------------------------------------------------------------------
        */

        $this->assertEquals(
            25.000,
            (float) $product->stock
        );
    }

    public function test_purchase_cannot_be_deleted(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $supplier = Supplier::create([
            'name' => 'Supplier Delete Test',
            'phone' => '08111111111',
            'address' => 'Test',
        ]);

        $purchase = Purchase::create([
            'invoice_no' => 'PO-TEST-0001',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 50000,
        ]);

        PurchaseDetail::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 5000,
            'subtotal' => 25000,
        ]);

        $this->expectException(\LogicException::class);

        $purchase->delete();
    }

    public function test_sale_cannot_be_deleted(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $sale = Sale::create([
            'invoice_no' => 'INV-TEST-0001',
            'user_id' => $user->id,
            'customer_id' => null,
            'subtotal' => 75000,
            'discount' => 0,
            'grand_total' => 75000,
            'payment_method' => 'Cash',
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'purchase_price' => 5000,
            'selling_price' => 7500,
            'subtotal' => 15000,
        ]);

        $this->expectException(\LogicException::class);

        $sale->delete();
    }

    public function test_stock_adjustment_cannot_be_updated_or_deleted(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $adjustment = StockAdjustment::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'system_stock' => 10,
            'physical_stock' => 9,
            'adjustment' => -1,
            'reason' => 'Barang rusak',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update harus ditolak
        |--------------------------------------------------------------------------
        */

        try {
            $adjustment->update([
                'reason' => 'Diubah',
            ]);

            $this->fail(
                'Stock Adjustment seharusnya tidak dapat diubah.'
            );
        } catch (\LogicException $e) {
            $this->assertStringContainsString(
                'tidak boleh diubah',
                $e->getMessage()
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delete harus ditolak
        |--------------------------------------------------------------------------
        */

        $this->expectException(\LogicException::class);

        $adjustment->delete();
    }

    public function test_stock_adjustment_must_have_correct_calculation(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $this->expectException(\LogicException::class);

        StockAdjustment::create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'system_stock' => 10,
            'physical_stock' => 8,
            /*
             * Seharusnya -2
             */
            'adjustment' => 5,
            'reason' => 'Data tidak valid',
        ]);
    }
}