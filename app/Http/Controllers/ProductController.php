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
        $search = $request->input('search');
        $sort = $request->input('sort', 'id'); 
        $direction = $request->input('direction', 'desc');

        // Kita gunakan leftJoin agar bisa melakukan sorting berdasarkan nama kategori
        $query = Product::with('category')
            ->select('products.*') // Wajib agar ID produk tidak tertimpa ID kategori
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->when($search, function ($q, $search) {
                $q->where('products.name', 'ilike', "%{$search}%")
                  ->orWhere('products.barcode', 'ilike', "%{$search}%");
            });

        // Logika Sorting Khusus
        if ($sort === 'category') {
            $query->orderBy('categories.name', $direction); // Urutkan nama abjad kategori
        } else {
            $query->orderBy('products.' . $sort, $direction); // Urutkan kolom produk lainnya
        }

        $products = $query->paginate(10)->withQueryString();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Create', [
            'categories' => Category::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'barcode' => 'nullable|string|unique:products,barcode',
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ]);

        Product::create($validated);
        return redirect()->route('products.index');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Edit', [
            'product' => $product,
            'categories' => Category::all()
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'name' => 'required|string|max:255',
            'unit' => 'required|string|max:50',
            'stock' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ]);

        $product->update($validated);
        return redirect()->route('products.index');
    }

    public function destroy(Product $product)
    {
        $product->delete(); // Ini akan melakukan Soft Delete
        return redirect()->route('products.index');
    }
}