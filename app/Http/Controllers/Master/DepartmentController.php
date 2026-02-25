<?php

namespace App\Http\Controllers\Master;

use App\Models\DocNumber;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\DepartmentRequest;
use App\Http\Resources\Master\DepartmentResource;

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
            $departments = Department::where('status', 1)->get();
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
            $department = Department::where('dep_code', $dep_code)->first();
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
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if department code already exists
            if (Department::where('dep_code', $data['dep_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Department')->first()->getDocCode();
                $data['dep_code'] = $docCode['code'];
            }

            // Handle image upload
            if ($request->hasFile('dep_image')) {
                $image = $request->file('dep_image');
                $filename = $data['dep_code'] . '.' . $image->getClientOriginalExtension();
                // $data['dep_image'] = $image->storeAs('departments', $filename, 'public');
                $data['dep_image'] = $image->storeAs('departments', $filename, 's3');
            } else {
                $data['dep_image'] = $data['dep_code'];
            }

            $department = Department::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => new DepartmentResource($department)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
            DB::beginTransaction();
            $department = Department::where('dep_code', $dep_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_dep_code = $data['dep_code'] ?? $dep_code;

            // If dep_code is changing, or if a new image is uploaded, the old image is invalid.
            if ((isset($data['dep_code']) && $data['dep_code'] !== $dep_code) || $request->hasFile('dep_image')) {
                if ($department->dep_image) {
                    // Storage::disk('public')->delete($department->dep_image);
                    Storage::disk('s3')->delete($department->dep_image);
                }
            }

            if ($request->hasFile('dep_image')) {
                $image = $request->file('dep_image');
                $filename = $new_dep_code . '.' . $image->getClientOriginalExtension();
                // $data['dep_image'] = $image->storeAs('departments', $filename, 'public');
                $data['dep_image'] = $image->storeAs('departments', $filename, 's3');
            } else {
                unset($data['dep_image']);
            }

            $department->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => new DepartmentResource($department)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
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
            $department = Department::where('dep_code', $dep_code)->first();
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
