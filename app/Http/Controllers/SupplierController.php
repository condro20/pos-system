<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
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
            'phone',
            'address',
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

        $query = Supplier::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where(
                        'name',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'phone',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'address',
                        'ilike',
                        "%{$search}%"
                    );
                });
            });

        // =========================================================
        // SORTING + PAGINATION
        // =========================================================

        $suppliers = $query
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        // =========================================================
        // RESPONSE
        // =========================================================

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Supplier::create($validated);

        return redirect()
            ->route('suppliers.index');
    }

    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $supplier->update($validated);

        return redirect()
            ->route('suppliers.index');
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()
            ->route('suppliers.index');
    }
}