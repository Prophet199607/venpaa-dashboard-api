<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\Publisher;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\PublisherRequest;
use App\Http\Resources\PublisherResource;

class PublisherController extends Controller
{
    public function generatePublisherCode()
    {
        try {
            $doc = DocNumber::where('type', 'Publisher')->first();

            if (!$doc) {
                // If no publisher document exists, create one
                $doc = DocNumber::create([
                    'type' => 'Publisher',
                    'prefix' => 'PUB',
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
            $publishers = Publisher::all();
            return response()->json([
                'success' => true,
                'message' => 'Publishers fetched successfully',
                'data' => PublisherResource::collection($publishers)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch publishers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function show($pub_code)
    {
        try {
            $publisher = Publisher::where('pub_code', $pub_code)->firstOrFail();
            return response()->json([
                'success' => true,
                'message' => 'Publisher fetched successfully',
                'data' => new PublisherResource($publisher)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publisher not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(PublisherRequest $request)
    {
        try {
            $data = $request->validated();

            // Check if publisher code already exists
            if (Publisher::where('pub_code', $data['pub_code'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Publisher code already exists'
                ], 422);
            }

            // Handle image upload
            if ($request->hasFile('pub_image')) {
                $imagePath = $request->file('pub_image')->store('publishers', 'public');
                $data['pub_image'] = $imagePath;
            }

            $data['created_by'] = auth()->id();
            $publisher = Publisher::create($data);

            $doc = DocNumber::where('type', 'Publisher')->first();
            if ($doc) {
                $doc->last_id += 1;
                $doc->save();
            }
            return response()->json([
                'success' => true,
                'message' => 'Publisher created successfully',
                'data' => new PublisherResource($publisher)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create publisher',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(PublisherRequest $request, $pub_code)
    {
        try {
            $publisher = Publisher::where('pub_code', $pub_code)->firstOrFail();
            $data = $request->validated();

            // Handle image update if provided
            if ($request->hasFile('pub_image')) {
                // Delete old image if exists
                if ($publisher->pub_image) {
                    Storage::disk('public')->delete($publisher->pub_image);
                }

                $imagePath = $request->file('pub_image')->store('publishers', 'public');
                $data['pub_image'] = $imagePath;
            }

            $data['updated_by'] = auth()->id();
            $publisher->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Publisher updated successfully',
                'data' => new PublisherResource($publisher)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update publisher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}