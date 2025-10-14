<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\SupplierResource;

class SupplierController extends Controller
{
    public function generateSupplierCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Supplier')->first()->getDocCode();

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
            $suppliers = Supplier::all();
            return response()->json([
                'success' => true,
                'message' => 'Suppliers fetched successfully',
                'data' => SupplierResource::collection($suppliers)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($sup_code)
    {
        try {
            $supplier = Supplier::where('sup_code', $sup_code)->firstOrFail();
            return response()->json([
                'success' => true,
                'message' => 'Supplier fetched successfully',
                'data' => new SupplierResource($supplier)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Supplier not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(SupplierRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Handle image upload
            if ($request->hasFile('sup_image')) {
                $imagePath = $request->file('sup_image')->store('suppliers', 'public');
                $data['sup_image'] = $imagePath;
            }

            // Check if supplier code already exists
            if (Supplier::where('sup_code', $data['sup_code'])->exists()) {
                $data['sup_code'] = DocNumber::where('type', 'Supplier')->first()->getDocCode();
            }

            $supplier = Supplier::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully',
                'data' => new SupplierResource($supplier)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(SupplierRequest $request, $sup_code)
    {
        try {
            $supplier = Supplier::where('sup_code', $sup_code)->firstOrFail();
            $data = $request->validated();

            // Handle image update if provided
            if ($request->hasFile('sup_image')) {
                // Delete old image if exists
                if ($supplier->sup_image) {
                    Storage::disk('public')->delete($supplier->sup_image);
                }

                $imagePath = $request->file('sup_image')->store('suppliers', 'public');
                $data['sup_image'] = $imagePath;
            }

            $data['updated_by'] = auth()->id();
            $supplier->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Supplier updated successfully',
                'data' => new SupplierResource($supplier)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}