<?php

namespace App\Http\Controllers\Master;

use App\Models\Location;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\LocationRequest;
use App\Http\Resources\Master\LocationResource;

class LocationController extends Controller
{
    public function generateLocationCode(Request $request)
    {
        try {
            $docCode = DocNumber::where('type', 'Location')->first()->getDocCode();

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $docCode['code']
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index()
    {
        try {
            $locations = Location::where('is_active', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Locations fetched successfully',
                'data' => LocationResource::collection($locations)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($loca_code)
    {
        try {
            $location = Location::where('loca_code', $loca_code)->first();

            return response()->json([
                'success' => true,
                'message' => 'Location fetched successfully',
                'data' => new LocationResource($location)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(LocationRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Handle boolean conversion from FormData
            if (isset($data['is_active'])) {
                $data['is_active'] = (int) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }

            // Check if location code already exists
            if (Location::where('loca_code', $data['loca_code'])->exists()) {
                $data['loca_code'] = DocNumber::where('type', 'Location')->first()->getDocCode();
            }

            $location = Location::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => new LocationResource($location)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(LocationRequest $request, $loca_code)
    {
        try {
            $data = $request->validated();

            if (isset($data['is_active'])) {
                $data['is_active'] = (int) filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
            }

            $location = Location::where('loca_code', $loca_code)->first();
            $data['updated_by'] = auth()->id();
            $location->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => new LocationResource($location)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
