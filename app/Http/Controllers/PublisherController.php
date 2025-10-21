<?php

namespace App\Http\Controllers;

use App\Models\DocNumber;
use App\Models\Publisher;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\PublisherRequest;
use App\Http\Resources\PublisherResource;

class PublisherController extends Controller
{
    public function generatePublisherCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Publisher')->first()->getDocCode();

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
            $publishers = Publisher::where('status', 1)->get();
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
            $publisher = Publisher::where('pub_code', $pub_code)->first();
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
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if publisher code already exists
            if (Publisher::where('pub_code', $data['pub_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Publisher')->first()->getDocCode();
                $data['pub_code'] = $docCode['code'];
            }

            // Handle image upload
            if ($request->hasFile('pub_image')) {
                $image = $request->file('pub_image');
                $filename = $data['pub_code'] . '.' . $image->getClientOriginalExtension();
                $data['pub_image'] = $image->storeAs('publishers', $filename, 'public');
            } else {
                $data['pub_image'] = $data['pub_code'];
            }

            $publisher = Publisher::create($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Publisher created successfully',
                'data' => new PublisherResource($publisher)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
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
            DB::beginTransaction();
            $publisher = Publisher::where('pub_code', $pub_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_pub_code = $data['pub_code'] ?? $pub_code;

            // If pub_code is changing, or if a new image is uploaded, the old image is invalid.
            if ((isset($data['pub_code']) && $data['pub_code'] !== $pub_code) || $request->hasFile('pub_image')) {
                if ($publisher->pub_image) {
                    Storage::disk('public')->delete($publisher->pub_image);
                }
            }

            if ($request->hasFile('pub_image')) {
                $image = $request->file('pub_image');
                $filename = $new_pub_code . '.' . $image->getClientOriginalExtension();
                $data['pub_image'] = $image->storeAs('publishers', $filename, 'public');
            } else {
                $data['pub_image'] = $new_pub_code;
            }

            $publisher->update($data);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Publisher updated successfully',
                'data' => new PublisherResource($publisher)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update publisher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
