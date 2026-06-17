<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductCategoryController extends Controller
{
    public function index()
    {
        $tenant = $this->authTenant();
        $categories = ProductCategory::where('tenant_id', $tenant->id)
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return view('admin.inventory.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $tenant = $this->authTenant();

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        ProductCategory::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'slug'      => Str::slug($data['name']),
        ]));

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, ProductCategory $category)
    {
        $tenant = $this->authTenant();
        abort_if($category->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'is_active'  => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ProductCategory $category)
    {
        $tenant = $this->authTenant();
        abort_if($category->tenant_id !== $tenant->id, 403);

        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete a category with products.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
