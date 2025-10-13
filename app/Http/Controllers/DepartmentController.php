<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\Department;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\DepartmentRequest;
use App\Http\Resources\DepartmentResource;

class DepartmentController extends Controller
{
    public function generateDepartmentCode()
    {
        try {
            $doc = DocNumber::where('type', 'Department')->first();

            if (!$doc) {
                // If no department document exists, create one
                $doc = DocNumber::create([
                    'type' => 'Department',
                    'prefix' => 'DEP',
                    'last_id' => 0
                ]);
            }

            $nextId = $doc->last_id + 1;
            $number = $doc->prefix . str_pad($nextId, 3, '0', STR_PAD_LEFT);

            return response()->json([
                'success' => true,
                'message' => 'Code generated successfully',
                'code' => $number
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
            $departments = Department::all();
            return response()->json([
                'success' => true,
                'message' => 'Departments fetched successfully',
                'data' => DepartmentResource::collection($departments)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($dep_code)
    {
        try {
            $department = Department::where('dep_code', $dep_code)->firstOrFail();
            return response()->json([
                'success' => true,
                'message' => 'Department fetched successfully',
                'data' => new DepartmentResource($department)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(DepartmentRequest $request)
    {
        try {
            $data = $request->validated();

            // Check if department code already exists
            if (Department::where('dep_code', $data['dep_code'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department code already exists'
                ], 422);
            }

            // Handle image upload
            if ($request->hasFile('dep_image')) {
                $imagePath = $request->file('dep_image')->store('departments', 'public');
                $data['dep_image'] = $imagePath;
            }

            $data['created_by'] = auth()->id();
            $department = Department::create($data);

            $doc = DocNumber::where('type', 'Department')->first();
            if ($doc) {
                $doc->last_id += 1;
                $doc->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => new DepartmentResource($department)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(DepartmentRequest $request, $dep_code)
    {
        try {
            $department = Department::where('dep_code', $dep_code)->firstOrFail();
            $data = $request->validated();

            // Handle image update if provided
            if ($request->hasFile('dep_image')) {
                // Delete old image if exists
                if ($department->dep_image) {
                    Storage::disk('public')->delete($department->dep_image);
                }

                $imagePath = $request->file('dep_image')->store('departments', 'public');
                $data['dep_image'] = $imagePath;
            }

            $data['updated_by'] = auth()->id();
            $department->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => new DepartmentResource($department)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function categories($dep_code)
    {
        try {
            $department = Department::where('dep_code', $dep_code)->firstOrFail();
            $categories = $department->categories;

            return response()->json([
                'success' => true,
                'message' => 'Categories fetched successfully',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}