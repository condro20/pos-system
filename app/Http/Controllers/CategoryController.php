<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $sort = $request->input('sort', 'id'); // Default urut berdasarkan ID terbaru
        $direction = $request->input('direction', 'desc');

        // Query data kategori
        $query = Category::query()
            ->when($search, function ($q, $search) {
                $q->where('name', 'ilike', "%{$search}%");
            });

        // Terapkan sorting dan pagination
        $categories = $query->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
        ]);

        Category::create($validated);
        return redirect()->route('categories.index');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Categories/Edit', [
            'category' => $category
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ]);

        $category->update($validated);
        return redirect()->route('categories.index');
    }

    public function destroy(Category $category)
    {
        // Opsional: Anda bisa menambahkan pengecekan di sini agar kategori 
        // tidak bisa dihapus jika masih ada produk yang memakainya.
        $category->delete();
        return redirect()->route('categories.index');
    }
}