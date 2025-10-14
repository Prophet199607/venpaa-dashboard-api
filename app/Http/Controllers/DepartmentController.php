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
            $docCode = DocNumber::where('type', 'Department')->first()->getDocCode();

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
            $data['created_by'] = auth()->id();

            // Handle image upload
            if ($request->hasFile('dep_image')) {
                $imagePath = $request->file('dep_image')->store('departments', 'public');
                $data['dep_image'] = $imagePath;
            }

            // Check if department code already exists
            if (Department::where('dep_code', $data['dep_code'])->exists()) {
                $data['dep_code'] = DocNumber::where('type', 'Department')->first()->getDocCode();
            }

            $department = Department::create($data);

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