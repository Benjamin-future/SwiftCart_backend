<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // ✅ Get all products (anyone can view — admin sees everything here too)
    public function index()
    {
        $this->authorize('viewAny', Product::class);

        $products = Product::orderByDesc('id')->get();

        return response()->json($products, 200);
    }

    // ✅ Store new product
    // - Admin: may create a product for the platform itself (vendor_id
    //   stays null) OR assign it to a specific vendor by passing
    //   vendor_id in the payload.
    // - Vendor: vendor_id is force-set to their own id, ignoring
    //   anything sent in the request — a vendor should never be able
    //   to create a product under someone else's name.
    public function store(Request $request)
    {
        // FIX: this used to call $this->authorize('create', Product::class),
        // which delegates to ProductPolicy — a file I've never seen the
        // contents of. It almost certainly only allows admins, which is
        // why a vendor hitting this same method via POST /vendor/products
        // got "This action is unauthorized." Explicit check instead,
        // consistent with how updateOwn()/destroyOwn() already do
        // ownership checks inline rather than through a Policy.
        if (!$this->isAdmin($request) && !$this->isVendor($request)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'stock'       => 'nullable|integer|min:0',
            // can be a filename (string) OR uploaded file; we normalize
            'image'       => 'nullable',
            // only relevant when an admin is assigning the product to
            // a vendor — vendors can't set this themselves
            'vendor_id'   => 'nullable|exists:users,id',
        ]);

        $filename = $this->extractImageFilename($request);
        if ($filename !== null) {
            $validated['image'] = $filename; // e.g. "laptop.png"
        } else {
            $validated['image'] = isset($validated['image']) && is_string($validated['image'])
                ? $this->sanitizeFilename($validated['image'])
                : null;
        }

        $validated['vendor_id'] = $this->isVendor($request)
            ? $request->user()->id
            : ($validated['vendor_id'] ?? null);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    // ✅ Show single product (anyone can view)
    public function show(Product $product)
    {
        $this->authorize('view', $product);
        return response()->json($product, 200);
    }

    // ✅ Update product (admin — any product)
    public function update(Request $request, Product $product)
    {
        $this->authorize('update', $product);

        $validated = $this->validateProduct($request);

        $filename = $this->extractImageFilename($request);
        if ($filename !== null) {
            $validated['image'] = $filename;
        } elseif (array_key_exists('image', $validated)) {
            $validated['image'] = is_string($validated['image'])
                ? $this->sanitizeFilename($validated['image'])
                : $product->image;
        }

        $product->update($validated);

        return response()->json($product, 200);
    }

    // ✅ Delete product (admin — any product)
    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->json(['message' => 'Product deleted'], 200);
    }

    /* =========================================================
     |  VENDOR-SCOPED endpoints
     |  Routes (behind 'vendor' middleware, prefix /vendor):
     |   GET    /vendor/products
     |   PUT    /vendor/products/{product}
     |   DELETE /vendor/products/{product}
     |  (POST /vendor/products reuses store() above, since the
     |  vendor_id-forcing logic already lives there)
     * ========================================================*/

    // List only the logged-in vendor's own products.
    public function vendorIndex(Request $request)
    {
        $products = Product::where('vendor_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($products, 200);
    }

    // Update — but only if this vendor actually owns the product.
    // Never trust the {product} route param for ownership; always
    // check vendor_id against the authenticated user.
    public function updateOwn(Request $request, Product $product)
    {
        if ($product->vendor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not own this product.',
            ], 403);
        }

        $validated = $this->validateProduct($request);

        $filename = $this->extractImageFilename($request);
        if ($filename !== null) {
            $validated['image'] = $filename;
        } elseif (array_key_exists('image', $validated)) {
            $validated['image'] = is_string($validated['image'])
                ? $this->sanitizeFilename($validated['image'])
                : $product->image;
        }

        // A vendor can never reassign vendor_id via this endpoint.
        unset($validated['vendor_id']);

        $product->update($validated);

        return response()->json($product, 200);
    }

    // Delete — same ownership check as updateOwn().
    public function destroyOwn(Request $request, Product $product)
    {
        if ($product->vendor_id !== $request->user()->id) {
            return response()->json([
                'message' => 'You do not own this product.',
            ], 403);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted'], 200);
    }

    /* ----------------- helpers ----------------- */

    private function isVendor(Request $request): bool
    {
        return $request->user()?->role === 'vendor';
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        return $user?->role === 'admin' || $user?->is_super_admin === true;
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'stock'       => 'nullable|integer|min:0',
            'image'       => 'nullable',
            'vendor_id'   => 'nullable|exists:users,id',
        ]);
    }

    /**
     * Extract a filename from either an uploaded file or a string field.
     * - If a file was uploaded under "image", return its original filename (e.g., "phone.jpg")
     * - If "image" is a string, sanitize and return just the basename
     * - Otherwise return null
     */
    private function extractImageFilename(Request $request): ?string
    {
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                return $this->sanitizeFilename($file->getClientOriginalName());
            }
            return null;
        }

        $raw = $request->input('image');
        if (is_string($raw) && strlen(trim($raw)) > 0) {
            return $this->sanitizeFilename($raw);
        }

        return null;
    }

    /**
     * Keep only the base filename and strip any directory components.
     * Also trims leading/trailing spaces to prevent odd paths like "/images// file.png".
     */
    private function sanitizeFilename(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('#^/?images/#i', '', $value);
        return basename($value);
    }
}