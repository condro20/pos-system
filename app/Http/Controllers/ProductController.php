<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = trim(
            (string) $request->input('search', '')
        );

        $allowedSorts = [
            'id',
            'barcode',
            'name',
            'stock',
            'purchase_price',
            'selling_price',
            'category',
        ];

        $sort = $request->input('sort', 'id');

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $direction = strtolower(
            $request->input('direction', 'desc')
        );

        if (!in_array(
            $direction,
            ['asc', 'desc'],
            true
        )) {
            $direction = 'desc';
        }

        $query = Product::with('category')
            ->select('products.*')
            ->leftJoin(
                'categories',
                'products.category_id',
                '=',
                'categories.id'
            )
            ->when(
                $search !== '',
                function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where(
                            'products.name',
                            'ilike',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'products.barcode',
                            'ilike',
                            "%{$search}%"
                        );
                    });
                }
            );

        if ($sort === 'category') {
            $query->orderBy(
                'categories.name',
                $direction
            );
        } else {
            $query->orderBy(
                'products.' . $sort,
                $direction
            );
        }

        $products = $query
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,barcode',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'unit' => [
                'required',
                'string',
                'max:50',
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        $barcode = trim(
            (string) ($validated['barcode'] ?? '')
        );

        $name = trim(
            $validated['name']
        );

        $unit = trim(
            $validated['unit']
        );

        Product::create([
            'category_id' => $validated['category_id'],
            'barcode' => $barcode !== ''
                ? $barcode
                : null,
            'name' => $name,
            'unit' => $unit,

            /*
             * Stok tidak boleh dimasukkan melalui Product.
             *
             * Stok awal dilakukan melalui Stock Adjustment.
             */
            'purchase_price' => round(
                (float) $validated['purchase_price'],
                2
            ),
            'selling_price' => round(
                (float) $validated['selling_price'],
                2
            ),
        ]);

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil ditambahkan. Stok awal dapat dimasukkan melalui Stock Adjustment.'
            );
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(
        Request $request,
        Product $product
    ) {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                'exists:categories,id',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:255',
                'unique:products,barcode,' . $product->id,
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'unit' => [
                'required',
                'string',
                'max:50',
            ],

            'purchase_price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        $barcode = trim(
            (string) ($validated['barcode'] ?? '')
        );

        $product->update([
            'category_id' => $validated['category_id'],

            'barcode' => $barcode !== ''
                ? $barcode
                : null,

            'name' => trim(
                $validated['name']
            ),

            'unit' => trim(
                $validated['unit']
            ),

            /*
             * STOCK SENGAJA TIDAK DIUBAH.
             */
            'purchase_price' => round(
                (float) $validated['purchase_price'],
                2
            ),

            'selling_price' => round(
                (float) $validated['selling_price'],
                2
            ),
        ]);

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil diperbarui.'
            );
    }

    public function destroy(Product $product)
    {
        /*
         * Jangan hapus produk yang masih mempunyai stok.
         */
        if ((float) $product->stock > 0) {
            return redirect()
                ->route('products.index')
                ->with(
                    'error',
                    'Produk tidak dapat dihapus karena masih memiliki stok. Habiskan atau sesuaikan stok terlebih dahulu.'
                );
        }

        /*
         * Jangan hapus produk yang sudah pernah terjual.
         */
        if ($product->saleDetails()->exists()) {
            return redirect()
                ->route('products.index')
                ->with(
                    'error',
                    'Produk tidak dapat dihapus karena sudah memiliki histori penjualan.'
                );
        }

        /*
         * Jangan hapus produk yang pernah dibeli.
         */
        if ($product->purchaseDetails()->exists()) {
            return redirect()
                ->route('products.index')
                ->with(
                    'error',
                    'Produk tidak dapat dihapus karena sudah memiliki histori pembelian.'
                );
        }

        /*
         * Jangan hapus produk yang memiliki histori opname.
         */
        if ($product->stockAdjustments()->exists()) {
            return redirect()
                ->route('products.index')
                ->with(
                    'error',
                    'Produk tidak dapat dihapus karena sudah memiliki histori Stock Adjustment.'
                );
        }

        $product->delete();

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil dihapus.'
            );
    }
}