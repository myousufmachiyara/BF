<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MeasurementUnit;
use App\Models\ProductPart;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')->get();        
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = ProductCategory::all();
        $units = MeasurementUnit::all();
        $allProducts = Product::where('is_active', 1)->get();

        $allProducts = Product::where('is_active', 1)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        return view('products.create', compact('categories', 'units', 'allProducts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:products,name',
            'category_id' => 'required|exists:product_categories,id',
            'sku' => 'required|string|unique:products,sku',
            'description' => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'selling_price' => 'nullable|numeric',
            'purchase_price' => 'nullable|numeric',
            'bilty_charges' => 'nullable|numeric',
            'opening_stock' => 'required|numeric',
            'reorder_level' => 'nullable|numeric',
            'max_stock_level' => 'nullable|numeric',
            'minimum_order_qty' => 'nullable|numeric',
            'is_active' => 'boolean',
            'prod_att.*' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'parts' => 'nullable|array',
            'parts.*.part_id' => 'nullable|exists:products,id',
            'parts.*.quantity' => 'nullable|numeric',
        ]);

        DB::beginTransaction();

        try {
            // ✅ Create Product
            $productData = $request->only([
                'name', 'category_id','sku', 'description','measurement_unit','opening_stock', 'selling_price', 'purchase_price', 
                'bilty_charges', 'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
            ]);

            $product = Product::create($productData);
            Log::info('[Product Store] Product created', ['product_id' => $product->id, 'data' => $productData]);

            // ✅ Upload Images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                    Log::info('[Product Store] Image uploaded', ['product_id' => $product->id, 'path' => $path]);
                }
            }

            // ✅ Save Product Parts (BOM with Variations)
            if ($request->filled('parts')) {
                foreach ($request->parts as $part) {
                    if (empty($part['part_id'])) {
                        continue; // skip empty rows
                    }

                    if ($part['part_id'] == $product->id) {
                        throw new \Exception('Product cannot be added as its own part.');
                    }

                    // Validate variation belongs to part
                    if (!empty($part['part_variation_id'])) {
                        $variation = ProductVariation::where('id', $part['part_variation_id'])
                            ->where('product_id', $part['part_id'])
                            ->first();

                        if (!$variation) {
                            throw new \Exception('Selected variation does not belong to selected part.');
                        }
                    }

                    ProductPart::create([
                        'product_id' => $product->id,
                        'part_id' => $part['part_id'],
                        'part_variation_id' => $part['part_variation_id'] ?? null,
                        'quantity' => $part['quantity'],
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product Store] Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all()
            ]);
            return back()->withInput()->with('error', 'Product creation failed. Check logs for details.');
        }
    }

    public function show(Product $product)
    {
        return redirect()->route('products.index');
    }
    
    public function details(Request $request)
    {
        $product = Product::findOrFail($request->id);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'code' => $product->item_code ?? '',      // If you have `item_code`
            'unit' => $product->unit ?? '',           // If your table has `unit`
            'price' => $product->price ?? 0,          // Or get price from variation
        ]);
    }

    public function edit($id)
    {
        $product = Product::with([
            'images',
            'parts.part',              // 🔹 load part product
        ])->findOrFail($id);

        $categories = ProductCategory::all();
        $units = MeasurementUnit::all(); // ✅ Add this line

        $allProducts = Product::where('is_active', 1)
        ->select('id', 'name')
        ->orderBy('name')
        ->get();

        return view('products.edit', compact(
            'product',
            'allProducts',
            'categories',
            'units' // ✅ Pass to view
        ));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);

            // ✅ Update product
            $product->update($request->only([
                'name', 'category_id', 'sku', 'measurement_unit', 'opening_stock', 'description', 'bilty_charges', 
                'selling_price', 'purchase_price' , 'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
            ]));

            // ✅ Upload new images
            if ($request->hasFile('prod_att')) {
                foreach ($request->file('prod_att') as $file) {
                    $path = $file->store('products', 'public');
                    $product->images()->create(['image_path' => $path]);
                }
            }

            // ✅ Remove images
            if ($request->filled('removed_images')) {
                foreach ($request->removed_images as $id) {
                    $img = $product->images()->find($id);
                    if ($img) {
                        if (\Storage::disk('public')->exists($img->image_path)) {
                            \Storage::disk('public')->delete($img->image_path);
                        }
                        $img->delete();
                    }
                }
            }

            // 🔄 Update Product Parts (BOM)
            $product->parts()->delete(); // remove old parts

            if ($request->filled('parts')) {
                foreach ($request->parts as $part) {

                    if (empty($part['part_id'])) {
                        continue;
                    }

                    if ($part['part_id'] == $product->id) {
                        throw new \Exception('Product cannot be added as its own part.');
                    }

                    // Validate variation belongs to product
                    if (!empty($part['part_variation_id'])) {
                        $variation = ProductVariation::where('id', $part['part_variation_id'])
                            ->where('product_id', $part['part_id'])
                            ->first();

                        if (!$variation) {
                            throw new \Exception('Selected variation does not belong to selected part.');
                        }
                    }

                    ProductPart::create([
                        'product_id'        => $product->id,
                        'part_id'           => $part['part_id'],
                        'part_variation_id' => $part['part_variation_id'] ?? null,
                        'quantity'          => $part['quantity'] ?? 1,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('products.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Product Update] Failed', ['error' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Product update failed. Try again.');
        }
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product deleted successfully.');
    }

}

