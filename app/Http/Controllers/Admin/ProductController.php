<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\InventoryMovement;
use App\Services\FileStorageService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly FileStorageService $files) {}


    public function index(Request $request)
    {
        $tenant = $this->authTenant();

        $products = Product::where('tenant_id', $tenant->id)
            ->with('category')
            ->when($request->search, fn($q, $v) => $q->where(fn($w) =>
                $w->where('name', 'like', "%{$v}%")->orWhere('sku', 'like', "%{$v}%")))
            ->when($request->category, fn($q) => $q->where('category_id', $request->category))
            ->when($request->low_stock, fn($q) => $q->whereColumn('stock_quantity', '<=', 'low_stock_threshold'))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        $categories = ProductCategory::where('tenant_id', $tenant->id)->get();

        return view('admin.inventory.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $tenant = $this->authTenant();
        $categories = ProductCategory::where('tenant_id', $tenant->id)->get();

        return view('admin.inventory.products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $tenant   = $this->authTenant();
        $branchId = $this->requireActiveBranch('product');

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'category_id'         => 'required|exists:product_categories,id',
            'sku'                 => 'nullable|string|max:100',
            'barcode'             => 'nullable|string|max:100',
            'description'         => 'nullable|string',
            'cost_price'          => 'required|numeric|min:0',
            'selling_price'       => 'required|numeric|min:0',
            'stock_quantity'      => 'required|integer|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'track_inventory'     => 'boolean',
            'is_active'           => 'boolean',
            'image'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Store the image path (not the file itself) so the record stays
        // storage-driver agnostic; the upload goes through the central service.
        if ($request->hasFile('image')) {
            $data['image'] = $this->files->uploadFile(
                $request->file('image'),
                FileStorageService::FOLDER_PRODUCTS . '/' . $tenant->id,
            );
        }

        Product::create(array_merge($data, [
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
        ]));

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product)
    {
        $tenant = $this->authTenant();
        abort_if($product->tenant_id !== $tenant->id, 403);

        $categories = ProductCategory::where('tenant_id', $tenant->id)->get();

        return view('admin.inventory.products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $tenant = $this->authTenant();
        abort_if($product->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'name'                => 'required|string|max:255',
            'category_id'         => 'required|exists:product_categories,id',
            'sku'                 => 'nullable|string|max:100',
            'barcode'             => 'nullable|string|max:100',
            'description'         => 'nullable|string',
            'cost_price'          => 'required|numeric|min:0',
            'selling_price'       => 'required|numeric|min:0',
            'low_stock_threshold' => 'required|integer|min:0',
            'track_inventory'     => 'boolean',
            'is_active'           => 'boolean',
            'image'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_image'        => 'nullable|boolean',
        ]);

        // Image lifecycle: remove existing, OR replace with a new upload, OR
        // leave untouched. All paths funnel through the storage service.
        unset($data['remove_image']);
        if ($request->boolean('remove_image') && $product->image) {
            $this->files->deleteFile($product->image);
            $data['image'] = null;
        }
        if ($request->hasFile('image')) {
            $data['image'] = $this->files->replaceFile(
                $request->file('image'),
                $product->image,
                FileStorageService::FOLDER_PRODUCTS . '/' . $tenant->id,
            );
        }

        $product->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product)
    {
        $tenant = $this->authTenant();
        abort_if($product->tenant_id !== $tenant->id, 403);

        $product->update(['is_active' => false]);

        return redirect()->route('admin.products.index')->with('success', 'Product deactivated.');
    }

    public function adjustStock(Request $request, Product $product)
    {
        $tenant = $this->authTenant();
        abort_if($product->tenant_id !== $tenant->id, 403);

        $data = $request->validate([
            'type'     => 'required|in:restock,adjustment,damage,return',
            'quantity' => 'required|integer|min:1',
            'note'     => 'nullable|string|max:255',
        ]);

        $qty = in_array($data['type'], ['damage']) ? -abs($data['quantity']) : abs($data['quantity']);
        $product->adjustStock($qty, $data['type'], $data['note'] ?? null);

        return back()->with('success', 'Stock adjusted successfully.');
    }

    public function movements(Product $product)
    {
        $tenant = $this->authTenant();
        abort_if($product->tenant_id !== $tenant->id, 403);

        $movements = InventoryMovement::where('product_id', $product->id)
            ->with('createdBy')
            ->latest()
            ->paginate(30);

        return view('admin.inventory.products.movements', compact('product', 'movements'));
    }
}
