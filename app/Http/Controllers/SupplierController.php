<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Models\DocNumber;
use Illuminate\Support\Facades\DB;
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
            $suppliers = Supplier::where('status', 1)->get();
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
            $supplier = Supplier::where('sup_code', $sup_code)->first();
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
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if supplier code already exists
            if (Supplier::where('sup_code', $data['sup_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Supplier')->first()->getDocCode();
                $data['sup_code'] = $docCode['code'];
            }

            // Handle image upload
            if ($request->hasFile('sup_image')) {
                $image = $request->file('sup_image');
                $filename = $data['sup_code'] . '.' . $image->getClientOriginalExtension();
                $data['sup_image'] = $image->storeAs('suppliers', $filename, 'public');
            } else {
                $data['sup_image'] = $data['sup_code'];
            }

            $supplier = Supplier::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier created successfully',
                'data' => new SupplierResource($supplier)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
            DB::beginTransaction();
            $supplier = Supplier::where('sup_code', $sup_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_sup_code = $data['sup_code'] ?? $sup_code;

            // If sup_code is changing, or if a new image is uploaded, the old image is invalid.
            if ((isset($data['sup_code']) && $data['sup_code'] !== $sup_code) || $request->hasFile('sup_image')) {
                if ($supplier->sup_image) {
                    Storage::disk('public')->delete($supplier->sup_image);
                }
            }

            if ($request->hasFile('sup_image')) {
                $image = $request->file('sup_image');
                $filename = $new_sup_code . '.' . $image->getClientOriginalExtension();
                $data['sup_image'] = $image->storeAs('suppliers', $filename, 'public');
            } else {
                $data['sup_image'] = $new_sup_code;
            }

            $supplier->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Supplier updated successfully',
                'data' => new SupplierResource($supplier)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function changeSupplierStatus($sup_code, $status)
    {
        try {
            $supplier = Supplier::where('sup_code', $sup_code)->first();

            if (!$supplier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supplier not found',
                ], 404);
            }

            $supplier->update([
                'status' => $status,
                'updated_by' => auth()->id()
            ]);

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