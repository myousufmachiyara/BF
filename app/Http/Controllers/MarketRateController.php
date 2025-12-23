<?php

namespace App\Http\Controllers;

use App\Models\MarketRate;
use App\Models\ProductCategory;
use App\Models\ProductSubcategory;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class MarketRateController extends Controller
{
    public function index()
    {
        try {
            $rates = MarketRate::with(['category', 'subcategory', 'shape', 'size'])->get();

            $categories = ProductCategory::all();
            $subcategories = ProductSubcategory::all();
            $shapes = AttributeValue::whereHas('attribute', fn($q) => $q->where('name', 'Shape'))->get();
            $sizes = AttributeValue::whereHas('attribute', fn($q) => $q->where('name', 'Size'))->get();

            Log::info('MarketRate index loaded successfully.', ['count' => $rates->count()]);

            return view('products.market-rates', compact('rates', 'categories', 'subcategories', 'shapes', 'sizes'));
        } catch (Exception $e) {
            Log::error('MarketRate Index Error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Failed to load market rates.');
        }
    }

    public function show($id)
    {
        try {
            $rate = MarketRate::findOrFail($id);
            Log::info('MarketRate show', ['id' => $id]);
            return response()->json($rate);
        } catch (Exception $e) {
            Log::error('MarketRate Show Error', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Record not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id'    => 'required|exists:product_categories,id',
            'subcategory_id' => 'nullable|exists:product_subcategories,id',
            'shape_id'       => 'nullable|exists:attribute_values,id',
            'size_id'        => 'nullable|exists:attribute_values,id',
            'rate'           => 'required|numeric|min:0',
        ]);

        try {
            $rate = MarketRate::create($request->only(['category_id', 'subcategory_id', 'shape_id', 'size_id', 'rate']));

            Log::info('MarketRate created successfully.', [
                'id'   => $rate->id,
                'data' => $rate->toArray()
            ]);

            return redirect()->route('market_rates.index')->with('success', 'Market Rate added successfully.');
        } catch (Exception $e) {
            Log::error('MarketRate Store Error', [
                'request' => $request->all(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return redirect()->back()->withInput()->with('error', 'Failed to add Market Rate.');
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'category_id'    => 'required|exists:product_categories,id',
            'subcategory_id' => 'nullable|exists:product_subcategories,id',
            'shape_id'       => 'nullable|exists:attribute_values,id',
            'size_id'        => 'nullable|exists:attribute_values,id',
            'rate'           => 'required|numeric|min:0',
        ]);

        try {
            $marketRate = MarketRate::findOrFail($id);
            $marketRate->update($request->only(['category_id', 'subcategory_id', 'shape_id', 'size_id', 'rate']));

            Log::info('MarketRate updated successfully.', [
                'id'   => $marketRate->id,
                'data' => $marketRate->toArray()
            ]);

            return redirect()->route('market_rates.index')->with('success', 'Market Rate updated successfully.');
        } catch (Exception $e) {
            Log::error('MarketRate Update Error', [
                'id'      => $id,
                'request' => $request->all(),
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return redirect()->back()->withInput()->with('error', 'Failed to update Market Rate.');
        }
    }

    public function destroy($id)
    {
        try {
            $marketRate = MarketRate::findOrFail($id);
            $marketRate->delete();

            Log::info('MarketRate deleted successfully.', ['id' => $id]);

            return redirect()->route('market_rates.index')->with('success', 'Market Rate deleted successfully.');
        } catch (Exception $e) {
            Log::error('MarketRate Delete Error', [
                'id'      => $id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Failed to delete Market Rate.');
        }
    }
}
