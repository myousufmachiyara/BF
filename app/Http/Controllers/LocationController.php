<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Location;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LocationController extends Controller
{
    // List all locations
    public function index()
    {
        $locations = Location::all();
        return view('stock-transfer.locations', compact('locations'));
    }

    // Store a new location
    public function store(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:255|unique:locations,name',
                'code' => 'nullable|string|max:50',
            ]);

            $location = Location::create($request->only('name', 'code'));
            Log::info('Location created', ['location' => $location->toArray()]);

            return redirect()->route('locations.index')->with('success', 'Location created successfully.');
        } catch (\Throwable $e) {
            Log::error('[Location Store] Failed', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()->with('error', 'Failed to create location. Please try again.');
        }
    }

    // Update an existing location
    public function update(Request $request, $id)
    {
        $location = Location::findOrFail($id);

        try {
            
            // Validate request
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('locations', 'name')->ignore($location->id),
                ],
                'code' => 'nullable|string|max:50',
            ]);

            $location->update($request->only('name', 'code'));

            Log::info('[Location Update] Success', [
                'location_id' => $location->id,
                'data' => $request->only('name', 'code')
            ]);

            return redirect()->route('locations.index')
                            ->with('success', 'Location updated successfully.');
        } catch (\Throwable $e) {
            // Log the error for debugging
            Log::error('[Location Update] Failed', [
                'location_id' => $location->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                            ->withInput()
                            ->with('error', 'Failed to update location. Please try again.');
        }
    }

    // Delete a location
    public function destroy($id)
    {
        $location = Location::findOrFail($id);
        $location->delete();
        \Log::info('Location deleted', ['location_id' => $location->id]);

        return redirect()->route('locations.index')->with('success', 'Location deleted successfully.');
    }
}
