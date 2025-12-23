<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\MeasurementUnit;
use App\Models\ProductVariation;
use App\Models\ProductPart;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category', 'subcategory', 'variations')->get();        
        return view('products.index', compact('products'));
    }

    public function barcodeSelection()
    {
        $variations = ProductVariation::with('product')->get();
        return view('products.barcode-selection', compact('variations'));
    }

    public function generateMultipleBarcodes(Request $request)
    {
        try {
            // Validation
            $validator = Validator::make($request->all(), [
                'selected_variations'   => 'required|array|min:1',
                'selected_variations.*' => 'exists:product_variations,id',
                'quantity'              => 'required|array',
            ]);

            if ($validator->fails()) {
                Log::error('Barcode generation validation failed', [
                    'errors'  => $validator->errors(),
                    'request' => $request->all(),
                ]);
                return back()->withErrors($validator)->withInput();
            }

            $barcodes = [];

            foreach ($request->selected_variations as $variationId) {
                $qty = max(1, (int)($request->quantity[$variationId] ?? 1));
                $variation = ProductVariation::with('product')->findOrFail($variationId);

                $barcodeText = $variation->barcode ?? $variation->sku ?? 'NO-BARCODE';
                $price = number_format($variation->product->selling_price ?? 0, 2);

                $generator = new BarcodeGeneratorPNG();
                $barcodeImage = base64_encode(
                    $generator->getBarcode($barcodeText, $generator::TYPE_CODE_128)
                );

                for ($i = 0; $i < $qty; $i++) {
                    $barcodes[] = [
                        'product'      => $variation->product->name,
                        'variation'    => $variation->name ?? '',
                        'barcodeText'  => $barcodeText,
                        'barcodeImage' => $barcodeImage,
                        'price'        => $price,
                        'sku'          => $variation->sku,
                    ];
                }
            }

            return view('products.multiple-barcodes', compact('barcodes'));

        } catch (\Throwable $e) {
            Log::error('Exception while generating barcodes', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return back()->with('error', 'Something went wrong while generating barcodes. Check logs for details.');
        }
    }

    public function create()
    {
        $categories = ProductCategory::all();
        $subcategories = ProductSubcategory::all();
        $attributes = Attribute::with('values')->get();
        $units = MeasurementUnit::all();
        $allProducts = Product::where('is_active', 1)->get();

        $allProducts = Product::where('is_active', 1)
        ->select('id', 'name', 'item_type')
        ->orderBy('name')
        ->get();

        return view('products.create', compact('categories', 'subcategories' ,'attributes', 'units', 'allProducts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:products,name',
            'category_id' => 'required|exists:product_categories,id',
            'subcategory_id' => 'nullable|exists:product_subcategories,id',
            'sku' => 'required|string|unique:products,sku',
            'barcode' => 'nullable|string',
            'description' => 'nullable|string',
            'measurement_unit' => 'required|exists:measurement_units,id',
            'item_type' => 'required|in:fg,raw',
            'manufacturing_cost' => 'nullable|numeric',
            'consumption' => 'nullable|numeric',
            'selling_price' => 'nullable|numeric',
            'opening_stock' => 'required|numeric',
            'reorder_level' => 'nullable|numeric',
            'max_stock_level' => 'nullable|numeric',
            'minimum_order_qty' => 'nullable|numeric',
            'is_active' => 'boolean',
            'prod_att.*' => 'nullable|image|mimes:jpeg,png,jpg,webp',
            'parts' => 'nullable|array',
            'parts.*.part_id' => 'nullable|exists:products,id',
            'parts.*.part_variation_id' => 'nullable|exists:product_variations,id',
            'parts.*.quantity' => 'nullable|numeric',
        ]);

        DB::beginTransaction();

        try {
            // ✅ Create Product
            $productData = $request->only([
                'name', 'category_id', 'subcategory_id', 'sku', 'barcode', 'description',
                'measurement_unit', 'item_type', 'manufacturing_cost',
                'opening_stock', 'selling_price', 'consumption',
                'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
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

            // ✅ Variations (FG only)
            if ($request->has('variations')) {
                foreach ($request->variations as $variationData) {
                    $variation = $product->variations()->create([
                        'sku' => $variationData['sku'] ?? null,
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price' => $variationData['selling_price'] ?? 0,
                    ]);
                    Log::info('[Product Store] Variation created', ['variation_id' => $variation->id, 'product_id' => $product->id, 'data' => $variationData]);

                    // Attribute Values
                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                        Log::info('[Product Store] Variation attributes synced', [
                            'variation_id' => $variation->id,
                            'attributes' => $variationData['attributes']
                        ]);
                    }
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
            'variations.attributeValues',
            'parts.part',              // 🔹 load part product
            'parts.partVariation'          // 🔹 load variation
        ])->findOrFail($id);

        $categories = ProductCategory::all();
        $subcategories = ProductSubcategory::all();
        $attributes = Attribute::with('values')->get();
        $units = MeasurementUnit::all(); // ✅ Add this line

        $allProducts = Product::where('is_active', 1)
        ->select('id', 'name', 'item_type')
        ->orderBy('name')
        ->get();

        // Optional: attach parent attribute (if needed for UI or JS)
        $attributeValues = collect();
        foreach ($attributes as $attribute) {
            foreach ($attribute->values as $val) {
                $val->attribute = $attribute;
                $attributeValues->push($val);
            }
        }

        return view('products.edit', compact(
            'product',
            'allProducts',
            'categories',
            'subcategories',
            'attributes',
            'attributeValues',
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
                'name', 'category_id', 'subcategory_id', 'sku', 'measurement_unit', 'item_type',
                'manufacturing_cost', 'opening_stock', 'description', 'selling_price',
                'consumption', 'reorder_level', 'max_stock_level', 'minimum_order_qty', 'is_active'
            ]));

            $handledVariationIds = [];

            // ✅ Update existing variations
            if (is_array($request->variations)) {
                foreach ($request->variations as $variationData) {
                    $variation = ProductVariation::findOrFail($variationData['id']);
                    $variation->update([
                        'sku' => $variationData['sku'],
                        'manufacturing_cost' => $variationData['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $variationData['stock_quantity'] ?? 0,
                        'selling_price' => $variationData['selling_price'] ?? 0,
                    ]);

                    if (!empty($variationData['attributes'])) {
                        $variation->attributeValues()->sync($variationData['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            // ✅ Add new variations
            if (is_array($request->new_variations)) {
                foreach ($request->new_variations as $newVar) {
                    $variation = $product->variations()->create([
                        'sku' => $newVar['sku'],
                        'manufacturing_cost' => $newVar['manufacturing_cost'] ?? 0,
                        'stock_quantity' => $newVar['stock_quantity'] ?? 0,
                        'selling_price' => $newVar['selling_price'] ?? 0,
                    ]);

                    if (!empty($newVar['attributes'])) {
                        $variation->attributeValues()->sync($newVar['attributes']);
                    }

                    $handledVariationIds[] = $variation->id;
                }
            }

            // ✅ Remove deleted variations
            if ($request->filled('removed_variations')) {
                ProductVariation::whereIn('id', $request->removed_variations)->delete();
            }

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

    public function getByBarcode($barcode)
    {
        try {
            // 1️⃣ Check in ProductVariation
            $variation = ProductVariation::with('product')
                ->where('barcode', $barcode)
                ->first();

            if ($variation) {
                return response()->json([
                    'success' => true,
                    'type' => 'variation',
                    'variation' => [
                        'id' => $variation->id,
                        'product_id' => $variation->product_id,
                        'sku' => $variation->sku,
                        'barcode' => $variation->barcode,
                        'name' => $variation->product->name,
                        'm.cost' => $variation->product->manufacturing_cost,
                    ]
                ]);
            }

            // 2️⃣ Check in Product (raw, service, FG without variations)
            $product = Product::where('barcode', $barcode)->first();

            if ($product) {
                return response()->json([
                    'success' => true,
                    'type' => 'product',
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'barcode' => $product->barcode,
                        'sku' => $product->sku,
                        'm.cost' => $product->manufacturing_cost,
                    ]
                ]);
            }

            // 3️⃣ Not found
            return response()->json([
                'success' => false,
                'message' => 'No product or variation found for this barcode'
            ]);

        } catch (\Exception $e) {
            Log::error('Barcode lookup failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error occurred while searching barcode'
            ]);
        }
    }

    public function getVariations($productId)
    {
        $product = Product::with(['variations', 'measurementUnit'])->find($productId);

        if (!$product) {
            return response()->json([
                'success'   => false,
                'variation' => [],
            ]);
        }

        $unitId = optional($product->measurementUnit)->id;

        $variations = $product->variations->map(function ($v) use ($unitId) {
            return [
                'id'   => $v->id,
                'sku'  => $v->sku,
                'unit' => $unitId,
            ];
        });

        return response()->json([
            'success'   => true,
            'variation' => $variations,

            // Extra product info (useful for BOM & costing)
            'product'   => [
                'id'                 => $product->id,
                'name'               => $product->name,
                'manufacturing_cost' => $product->manufacturing_cost,
                'unit'               => $unitId,
            ],
        ]);
    }

    public function bulkUploadTemplate()
    {
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=product_bulk_template.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $columns = [
            'Product SKU', 'Product Name', 'Category ID', 'Unit ID', 'Item Type', 'Description',
            'Variation SKU', 'Variation Barcode', 'Variation Price', 'Variation Stock',
            'Image URL / Path'
        ];

        $callback = function() use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            // sample data
            fputcsv($file, ['CHR001', 'Office Chair', '1', '2', 'finished', 'Ergonomic chair',
                            'CHR001-B-M', '1234567890123', '5000', '20', 'images/chair1.jpg']);
            fputcsv($file, ['CHR001', 'Office Chair', '1', '2', 'finished', 'Ergonomic chair',
                            'CHR001-W-L', '1234567890124', '5200', '15', 'images/chair2.jpg']);
            fputcsv($file, ['TBL001', 'Desk Table', '2', '3', 'finished', 'Large wooden desk',
                            'TBL001-W-L', '1234567890125', '8000', '10', 'images/table1.jpg']);

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    public function bulkUploadStore(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,csv,txt'
        ]);

        DB::beginTransaction();

        try {
            \Log::info('Bulk Upload started.');

            // Get file path
            $path = $request->file('file')->getRealPath();
            \Log::info('File uploaded', ['path' => $path]);

            // Read file with Laravel Excel
            $rows = Excel::toArray([], $path)[0] ?? [];
            \Log::info('File parsed', ['row_count' => count($rows)]);

            if (empty($rows)) {
                throw new \Exception('Uploaded file is empty.');
            }

            // Skip header row
            $header = array_shift($rows);
            \Log::info('Header row detected', ['header' => $header]);

            foreach ($rows as $index => $row) {
                if (empty($row[0]) && empty($row[1])) {
                    \Log::warning("Row {$index} skipped (empty).");
                    continue;
                }

                \Log::info("Processing row {$index}", ['row' => $row]);

                // Map row values safely
                $productSku        = trim($row[0] ?? '');
                $productName       = trim($row[1] ?? '');
                $categoryId        = (int) ($row[2] ?? 0);
                $unitId            = (int) ($row[3] ?? 0);
                $itemType          = trim($row[4] ?? '');
                $description       = $row[5] ?? null;
                $variationSku      = trim($row[6] ?? '');
                $variationBarcode  = $row[7] ?? null;
                $varPrice          = isset($row[8]) ? (float) $row[8] : 0;
                $varStock          = isset($row[9]) ? (float) $row[9] : 0;
                $imagePath         = $row[10] ?? null;

                // 1️⃣ Create or get Product
                $product = Product::firstOrCreate(
                    ['sku' => $productSku],
                    [
                        'name'              => $productName,
                        'category_id'       => $categoryId,
                        'measurement_unit'  => $unitId,
                        'item_type'         => $itemType,
                        'description'       => $description,
                        'manufacturing_cost'=> 0,
                        'opening_stock'     => 0,
                        'selling_price'     => 0,
                    ]
                );
                \Log::info("Product saved", ['product_id' => $product->id, 'sku' => $productSku]);

                // 2️⃣ Create Variation
                $variation = ProductVariation::updateOrCreate(
                    ['sku' => $variationSku],
                    [
                        'product_id'         => $product->id,
                        'barcode'            => $variationBarcode,
                        'selling_price'      => $varPrice,
                        'stock_quantity'     => $varStock,
                        'manufacturing_cost' => 0,
                    ]
                );
                \Log::info("Variation saved", ['variation_id' => $variation->id, 'sku' => $variationSku]);

                // 3️⃣ Attach image
                if ($imagePath) {
                    $image = $product->images()->firstOrCreate(['image_path' => $imagePath]);
                    \Log::info("Image attached", ['image_id' => $image->id, 'path' => $imagePath]);
                }
            }

            DB::commit();
            \Log::info('Bulk Upload completed successfully.');
            return back()->with('success', 'Bulk products uploaded successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Bulk upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Bulk upload failed: ' . $e->getMessage());
        }
    }

}

