<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $segment = (string) $request->query('segment', 'all');
        if (! in_array($segment, ['all', 'buyers', 'accounts', 'guests', 'dormant', 'inactive'], true)) {
            $segment = 'all';
        }

        $openCustomerId = $request->query('open');

        $base = User::query()->where('is_admin', false);

        $stats = [
            'total' => (clone $base)->count(),
            'buyers' => (clone $base)->has('orders')->count(),
            'accounts' => (clone $base)->where('is_guest', false)->count(),
            'guests' => (clone $base)->where('is_guest', true)->count(),
            'dormant' => (clone $base)->doesntHave('orders')->count(),
            'new_month' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
        ];

        $query = User::query()
            ->where('is_admin', false)
            ->withCount('orders')
            ->withSum('orders as orders_total', 'total_amount')
            ->with([
                'orders' => function ($orders) {
                    $orders->latest('placed_at')
                        ->latest('created_at')
                        ->limit(6)
                        ->withCount('items');
                },
                'addresses' => function ($addresses) {
                    $addresses->latest()->limit(8);
                },
            ]);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhereHas('addresses', function ($addresses) use ($search) {
                        $addresses->where('line_1', 'like', '%'.$search.'%')
                            ->orWhere('city', 'like', '%'.$search.'%')
                            ->orWhere('place_name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        match ($segment) {
            'buyers' => $query->has('orders'),
            'accounts' => $query->where('is_guest', false),
            'guests' => $query->where('is_guest', true),
            'dormant' => $query->doesntHave('orders'),
            'inactive' => $query->where('is_active', false),
            default => null,
        };

        $customers = $query
            ->orderByDesc('created_at')
            ->paginate(24)
            ->withQueryString();

        return view('admin.customers.index', compact(
            'customers',
            'stats',
            'segment',
            'openCustomerId',
        ));
    }

    public function show($id)
    {
        $customer = User::query()
            ->where('is_admin', false)
            ->with(['orders', 'addresses'])
            ->findOrFail($id);

        return view('admin.customers.show', compact('customer'));
    }

    public function create()
    {
        return view('admin.customers.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'normalized_email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'], // hashed cast
            'is_guest' => false,
            'is_active' => true,
        ]);

        return redirect()->route('admin.customers.index')->with('success', 'Customer created successfully.');
    }

    public function edit($id)
    {
        $customer = User::query()->where('is_admin', false)->findOrFail($id);

        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, $id)
    {
        $customer = User::query()->where('is_admin', false)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email,'.$id],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ]);

        $customer->name = $validated['name'];
        $customer->email = strtolower(trim($validated['email']));
        $customer->normalized_email = strtolower(trim($validated['email']));

        if (! empty($validated['password'])) {
            $customer->password = $validated['password']; // hashed cast — no bcrypt() wrapper
        }

        // Never allow elevating a customer to admin via this form.
        $customer->is_admin = false;
        $customer->save();

        return redirect()->route('admin.customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy($id)
    {
        $customer = User::query()->where('is_admin', false)->findOrFail($id);
        $orderCount = $customer->orders()->count();

        $customer->delete();

        $message = 'Customer deleted successfully.';
        if ($orderCount > 0) {
            $message .= ' '.$orderCount.' order'.($orderCount === 1 ? '' : 's').' remain in history (linked by email).';
        }

        return redirect()->route('admin.customers.index')->with('success', $message);
    }
}
