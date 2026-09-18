<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\TransactionNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionConcurrencyTest extends TestCase
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

    private function createProduct(float $stock = 100): Product
    {
        $category = Category::create([
            'name' => 'Concurrency Category',
        ]);

        return Product::create([
            'category_id' => $category->id,
            'barcode' => 'CONC-' . uniqid(),
            'name' => 'Produk Concurrency',
            'unit' => 'pcs',
            'stock' => $stock,
            'purchase_price' => 5000,
            'selling_price' => 7500,
        ]);
    }

    private function createSupplier(): Supplier
    {
        return Supplier::create([
            'name' => 'Supplier Concurrency',
            'phone' => '081234567890',
            'address' => 'Alamat Test',
        ]);
    }

    public function test_invoice_numbers_are_unique_and_sequential(): void
    {
        $service = app(
            TransactionNumberService::class
        );

        $numbers = [];

        DB::transaction(function () use (
            $service,
            &$numbers
        ) {
            $numbers[] = $service->generate('sale');
            $numbers[] = $service->generate('sale');
            $numbers[] = $service->generate('sale');
        });

        $this->assertSame([
            $numbers[0],
            $numbers[1],
            $numbers[2],
        ], array_values(array_unique($numbers)));

        $this->assertMatchesRegularExpression(
            '/^INV-\d{8}-\d{4}$/',
            $numbers[0]
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{8}-\d{4}$/',
            $numbers[1]
        );

        $this->assertMatchesRegularExpression(
            '/^INV-\d{8}-\d{4}$/',
            $numbers[2]
        );

        $this->assertSame(
            '0001',
            substr($numbers[0], -4)
        );

        $this->assertSame(
            '0002',
            substr($numbers[1], -4)
        );

        $this->assertSame(
            '0003',
            substr($numbers[2], -4)
        );
    }

    public function test_purchase_numbers_are_unique_and_sequential(): void
    {
        $service = app(
            TransactionNumberService::class
        );

        $numbers = [];

        DB::transaction(function () use (
            $service,
            &$numbers
        ) {
            $numbers[] = $service->generate('purchase');
            $numbers[] = $service->generate('purchase');
            $numbers[] = $service->generate('purchase');
        });

        $this->assertCount(
            3,
            array_unique($numbers)
        );

        $this->assertMatchesRegularExpression(
            '/^PO-\d{8}-\d{4}$/',
            $numbers[0]
        );

        $this->assertSame(
            '0001',
            substr($numbers[0], -4)
        );

        $this->assertSame(
            '0002',
            substr($numbers[1], -4)
        );

        $this->assertSame(
            '0003',
            substr($numbers[2], -4)
        );
    }

    public function test_failed_sale_rolls_back_invoice_sequence(): void
    {
        $service = app(
            TransactionNumberService::class
        );

        try {
            DB::transaction(function () use ($service) {
                $invoiceNo = $service->generate('sale');

                $this->assertSame(
                    '0001',
                    substr($invoiceNo, -4)
                );

                throw new \RuntimeException(
                    'Simulasi kegagalan checkout.'
                );
            });
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Simulasi kegagalan checkout.',
                $e->getMessage()
            );
        }

        /*
         * Karena sequence berada di transaction yang sama,
         * nomor 0001 harus tersedia kembali.
         */
        $nextInvoice = DB::transaction(
            fn () => $service->generate('sale')
        );

        $this->assertSame(
            '0001',
            substr($nextInvoice, -4)
        );
    }

    public function test_failed_purchase_rolls_back_purchase_sequence(): void
    {
        $service = app(
            TransactionNumberService::class
        );

        try {
            DB::transaction(function () use ($service) {
                $poNumber = $service->generate('purchase');

                $this->assertSame(
                    '0001',
                    substr($poNumber, -4)
                );

                throw new \RuntimeException(
                    'Simulasi kegagalan Purchase.'
                );
            });
        } catch (\RuntimeException $e) {
            $this->assertSame(
                'Simulasi kegagalan Purchase.',
                $e->getMessage()
            );
        }

        $nextPo = DB::transaction(
            fn () => $service->generate('purchase')
        );

        $this->assertSame(
            '0001',
            substr($nextPo, -4)
        );
    }

    public function test_sale_header_and_details_are_atomic(): void
    {
        $user = $this->createManager();

        $product = $this->createProduct(10);

        $beforeStock = (float) $product->stock;

        /*
         * Produk ID sengaja dibuat tidak valid.
         *
         * Validation harus gagal sebelum transaction dibuat.
         */
        $response = $this
            ->actingAs($user)
            ->postJson(
                route('pos.store'),
                [
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'qty' => 2,
                        ],
                        [
                            'product_id' => 999999,
                            'qty' => 2,
                        ],
                    ],
                    'payment_method' => 'Cash',
                ]
            );

        $response->assertStatus(422);

        $product->refresh();

        $this->assertEquals(
            $beforeStock,
            (float) $product->stock
        );

        $this->assertDatabaseCount(
            'sales',
            0
        );

        $this->assertDatabaseCount(
            'sale_details',
            0
        );
    }

    public function test_purchase_header_and_details_are_atomic(): void
    {
        $user = $this->createManager();

        $supplier = $this->createSupplier();

        $product = $this->createProduct(10);

        $beforeStock = (float) $product->stock;

        /*
         * Quantity kedua invalid.
         *
         * Validasi request terjadi sebelum transaction.
         */
        $response = $this
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
                        [
                            'product_id' => $product->id,
                            'qty' => 0,
                            'price' => 5000,
                        ],
                    ],
                ]
            );

        $response->assertSessionHasErrors();

        $product->refresh();

        $this->assertEquals(
            $beforeStock,
            (float) $product->stock
        );

        $this->assertDatabaseCount(
            'purchases',
            0
        );

        $this->assertDatabaseCount(
            'purchase_details',
            0
        );
    }

    public function test_database_rejects_duplicate_sale_invoice_number(): void
    {
        $user = $this->createManager();

        Sale::create([
            'invoice_no' => 'INV-TEST-0001',
            'user_id' => $user->id,
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'Cash',
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        Sale::create([
            'invoice_no' => 'INV-TEST-0001',
            'user_id' => $user->id,
            'customer_id' => null,
            'subtotal' => 10000,
            'discount' => 0,
            'grand_total' => 10000,
            'payment_method' => 'Cash',
        ]);
    }

    public function test_database_rejects_duplicate_purchase_invoice_number(): void
    {
        $user = $this->createManager();

        $supplier = $this->createSupplier();

        Purchase::create([
            'invoice_no' => 'PO-TEST-0001',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        Purchase::create([
            'invoice_no' => 'PO-TEST-0001',
            'user_id' => $user->id,
            'supplier_id' => $supplier->id,
            'grand_total' => 10000,
        ]);
    }
}