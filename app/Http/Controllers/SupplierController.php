<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    /* =========================
       INDEX
    ========================= */
    public function index(Request $request)
    {
        $suppliers = Supplier::query()
            ->when($request->search, fn ($q) => $q->where('suppliername', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('phonenumber', 'like', "%{$request->search}%")
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only('search'),
        ]);
    }

    /* =========================
       CREATE
    ========================= */
    public function create()
    {
        return Inertia::render('Suppliers/Create');
    }

    /* =========================
       STORE
    ========================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'suppliername' => 'required|string|max:255|unique:suppliers,suppliername',
            'phonenumber' => 'required|string|max:255|unique:suppliers,phonenumber',
            'email' => 'required|email|max:255|unique:suppliers,email',
        ]);

        Supplier::create($validated);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    /* =========================
       SHOW  ✅ THIS WAS MISSING
    ========================= */
    public function show(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
        ]);
    }

    /* =========================
       EDIT
    ========================= */
    public function edit(Supplier $supplier)
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => $supplier,
        ]);
    }

    /* =========================
       UPDATE
    ========================= */
    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'suppliername' => 'required|string|max:255|unique:suppliers,suppliername,'.$supplier->id,
            'phonenumber' => 'required|string|max:255|unique:suppliers,phonenumber,'.$supplier->id,
            'email' => 'required|email|max:255|unique:suppliers,email,'.$supplier->id,
        ]);

        $supplier->update($validated);

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier updated successfully.');
    }

    /* =========================
       DESTROY
    ========================= */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }
}
