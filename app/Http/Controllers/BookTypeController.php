<?php

namespace App\Http\Controllers;

use App\Models\BookType;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookTypeRequest;
use App\Http\Resources\BookTypeResource;

class BookTypeController extends Controller
{
    public function generateBookTypeCode(Request $request)
    {
        try {
            $docCode = DocNumber::where('type', 'BookType')->first()->getDocCode();

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
            $bookTypes = BookType::all();
            return response()->json([
                'success' => true,
                'message' => 'Book types fetched successfully',
                'data' => BookTypeResource::collection($bookTypes)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch book types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($bkt_code)
    {
        try {
            $bookType = BookType::where('bkt_code', $bkt_code)->firstOrFail();

            return response()->json([
                'success' => true,
                'message' => 'Book type fetched successfully',
                'data' => new BookTypeResource($bookType)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book type not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(BookTypeRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if book type code already exists
            if (BookType::where('bkt_code', $data['bkt_code'])->exists()) {
                $data['bkt_code'] = DocNumber::where('type', 'BookType')->first()->getDocCode();
            }

            $bookType = BookType::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Book type created successfully',
                'data' => new BookTypeResource($bookType)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create book type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(BookTypeRequest $request, $bkt_code)
    {
        try {
            $data = $request->validated();
            $bookType = BookType::where('bkt_code', $bkt_code)->firstOrFail();
            $data['updated_by'] = auth()->id();
            $bookType->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Book type updated successfully',
                'data' => new BookTypeResource($bookType)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update book type',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
