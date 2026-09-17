<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
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
            'email',
            'role',
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

        $query = User::with('role')
            ->select('users.*')
            ->leftJoin(
                'roles',
                'users.role_id',
                '=',
                'roles.id'
            )
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($query) use ($search) {
                    $query->where(
                        'users.name',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'users.email',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'roles.name',
                        'ilike',
                        "%{$search}%"
                    );
                });
            });

        // =========================================================
        // SORTING KHUSUS ROLE
        // =========================================================

        if ($sort === 'role') {
            $query->orderBy(
                'roles.name',
                $direction
            );
        } else {
            $query->orderBy(
                'users.' . $sort,
                $direction
            );
        }

        // =========================================================
        // PAGINATION
        // =========================================================

        $users = $query
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,

            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => [
                'required',
                'confirmed',
                Password::defaults(),
            ],
            'role_id' => 'required|exists:roles,id',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $request->role_id,
        ]);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Pengguna baru berhasil ditambahkan.'
            );
    }

    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'userEdit' => $user,
            'roles' => Role::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role_id' => 'required|exists:roles,id',
            'password' => [
                'nullable',
                'confirmed',
                Password::defaults(),
            ],
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role_id = $request->role_id;

        if ($request->filled('password')) {
            $user->password = Hash::make(
                $request->password
            );
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Data pengguna berhasil diperbarui.'
            );
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()
                ->back()
                ->withErrors([
                    'error' =>
                        'Anda tidak dapat menghapus akun Anda sendiri.',
                ]);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Pengguna berhasil dihapus.'
            );
    }
}