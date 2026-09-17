<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        // =========================================================
        // SORTING
        // =========================================================

        $allowedSorts = [
            'id',
            'name',
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

        // =========================================================
        // QUERY
        // =========================================================

        $query = Category::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(
                    'name',
                    'ilike',
                    "%{$search}%"
                );
            });

        // =========================================================
        // SORTING
        // =========================================================

        $categories = $query
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        // =========================================================
        // RESPONSE
        // =========================================================

        return Inertia::render('Categories/Index', [
            'categories' => $categories,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
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

        return redirect()
            ->route('categories.index');
    }

    public function edit(Category $category)
    {
        return Inertia::render('Categories/Edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ]);

        $category->update($validated);

        return redirect()
            ->route('categories.index');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()
            ->route('categories.index');
    }
}