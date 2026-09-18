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
        $search = trim((string) $request->input('search', ''));

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */

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

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        /*
        |--------------------------------------------------------------------------
        | QUERY
        |--------------------------------------------------------------------------
        */

        $query = Product::with('category')
            ->select('products.*')
            ->leftJoin(
                'categories',
                'products.category_id',
                '=',
                'categories.id'
            )
            ->when($search !== '', function ($q) use ($search) {
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
            });

        /*
        |--------------------------------------------------------------------------
        | SORTING KATEGORI
        |--------------------------------------------------------------------------
        */

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

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */

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

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::all(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    |
    | Produk baru selalu dibuat dengan stock = 0.
    |
    | Jika ingin memasukkan stok awal:
    | Product → buat produk
    | Stock Adjustment → masukkan stok fisik awal
    |
    */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'exists:categories,id',
            ],

            'barcode' => [
                'nullable',
                'string',
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
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        Product::create([
            'category_id' => $validated['category_id'],
            'barcode' => $validated['barcode'] ?? null,
            'name' => $validated['name'],
            'unit' => $validated['unit'],

            /*
             * Stock selalu dimulai dari 0.
             */
            'stock' => 0,

            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
        ]);

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil ditambahkan. Stok awal dapat dimasukkan melalui Stock Adjustment.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | EDIT
    |--------------------------------------------------------------------------
    */

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => Category::all(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    |
    | STOCK SENGAJA TIDAK ADA DI SINI.
    |
    */

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => [
                'required',
                'exists:categories,id',
            ],

            'barcode' => [
                'nullable',
                'string',
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
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | STOCK TIDAK DISENTUH
        |--------------------------------------------------------------------------
        */

        $product->update([
            'category_id' => $validated['category_id'],
            'barcode' => $validated['barcode'] ?? null,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'purchase_price' => $validated['purchase_price'],
            'selling_price' => $validated['selling_price'],
        ]);

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil diperbarui.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE PRODUCT
    |--------------------------------------------------------------------------
    */

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with(
                'success',
                'Produk berhasil dihapus.'
            );
    }
}