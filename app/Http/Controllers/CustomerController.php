<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerController extends Controller
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

        $query = Customer::query()
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

        $customers = $query
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        // =========================================================
        // RESPONSE
        // =========================================================

        return Inertia::render('Customers/Index', [
            'customers' => $customers,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        Customer::create($validated);

        return redirect()
            ->route('customers.index');
    }

    public function edit(Customer $customer)
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
        ]);

        $customer->update($validated);

        return redirect()
            ->route('customers.index');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()
            ->route('customers.index');
    }
}