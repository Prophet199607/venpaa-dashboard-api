<?php

namespace App\Http\Controllers\Master;

use App\Models\DocNumber;
use App\Models\Publisher;
use Illuminate\Http\Request;
use App\Imports\PublisherImport;
use App\Exports\PublisherExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;    
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\PublisherRequest;
use App\Http\Resources\Master\PublisherResource;


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
                // $data['pub_image'] = $image->storeAs('publishers', $filename, 'public');
                $data['pub_image'] = $image->storeAs('publishers', $filename, 's3');
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
                    // Storage::disk('public')->delete($publisher->pub_image);
                    Storage::disk('s3')->delete($publisher->pub_image);
                }
            }

            if ($request->hasFile('pub_image')) {
                $image = $request->file('pub_image');
                $filename = $new_pub_code . '.' . $image->getClientOriginalExtension();
                // $data['pub_image'] = $image->storeAs('publishers', $filename, 'public');
                $data['pub_image'] = $image->storeAs('publishers', $filename, 's3');
            } else {
                unset($data['pub_image']);
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

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->query('query');

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Publishers fetched successfully',
                    'data' => []
                ], 200);
            }

            $publishers = Publisher::where('status', 1)
                ->where(function ($query) use ($searchTerm) {
                    $query->where('pub_name', 'LIKE', '%' . $searchTerm . '%')
                        ->orWhere('pub_code', 'LIKE', '%' . $searchTerm . '%');
                })
                ->limit(100)
                ->get();

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
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            Excel::import(new PublisherImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Publishers imported successfully',
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $failures = $e->failures();
            $errorMessages = [];
            foreach ($failures as $failure) {
                $errorMessages[] = "Row " . $failure->row() . ": " . implode(', ', $failure->errors());
            }
            return response()->json([
                'success' => false,
                'message' => 'Validation failed during import',
                'error' => implode(' | ', array_slice($errorMessages, 0, 5)) . (count($errorMessages) > 5 ? ' ... and more' : '')
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import publishers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export()
    {
        try {
            return Excel::download(new PublisherExport, 'publishers.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export publishers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
