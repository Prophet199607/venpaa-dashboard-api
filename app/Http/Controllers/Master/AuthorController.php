<?php

namespace App\Http\Controllers\Master;

use App\Models\Author;
use App\Models\DocNumber;
use Illuminate\Http\Request;
use App\Imports\AuthorImport;
use App\Exports\AuthorExport;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Master\AuthorRequest;
use App\Http\Resources\Master\AuthorResource;

class AuthorController extends Controller
{
    public function generateAuthorCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Author')->first()->getDocCode();

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
            $authors = Author::where('status', 1)->get();
            return response()->json([
                'success' => true,
                'message' => 'Authors fetched successfully',
                'data' => AuthorResource::collection($authors)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch authors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($auth_code)
    {
        try {
            $author = Author::where('auth_code', $auth_code)->first();
            return response()->json([
                'success' => true,
                'message' => 'Author fetched successfully',
                'data' => new AuthorResource($author)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Author not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->input('query', '');

            $authors = Author::where('status', 1)
                ->where(function ($q) use ($query) {
                    $q->where('auth_name', 'LIKE', "%{$query}%")
                    ->orWhere('auth_code', 'LIKE', "%{$query}%");
                })
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Authors search results',
                'data' => AuthorResource::collection($authors),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search authors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(AuthorRequest $request)
    {
        try {
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Check if Author code already exists
            if (Author::where('auth_code', $data['auth_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Author')->first()->getDocCode();
                $data['auth_code'] = $docCode['code'];
            }

            // Handle image upload
            if ($request->hasFile('auth_image')) {
                $image = $request->file('auth_image');
                $filename = $data['auth_code'] . '.' . $image->getClientOriginalExtension();
                // $data['auth_image'] = $image->storeAs('authors', $filename, 'public');
                $data['auth_image'] = $image->storeAs('authors', $filename, 's3');
            } else {
                $data['auth_image'] = $data['auth_code'];
            }

            $author = Author::create($data);

            return response()->json([
                'success' => true,
                'message' => 'Author created successfully',
                'data' => new AuthorResource($author)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create author',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(AuthorRequest $request, $auth_code)
    {
        try {
            $author = Author::where('auth_code', $auth_code)->first();
            $data = $request->validated();
            $data['updated_by'] = auth()->id();

            $new_auth_code = $data['auth_code'] ?? $auth_code;

            // If auth_code is changing, or if a new image is uploaded, the old image is invalid.
            if ((isset($data['auth_code']) && $data['auth_code'] !== $auth_code) || $request->hasFile('auth_image')) {
                if ($author->auth_image) {
                    // Storage::disk('public')->delete($author->auth_image);
                    Storage::disk('s3')->delete($author->auth_image);
                }
            }

            if ($request->hasFile('auth_image')) {
                $image = $request->file('auth_image');
                $filename = $new_auth_code . '.' . $image->getClientOriginalExtension();
                // $data['auth_image'] = $image->storeAs('authors', $filename, 'public');
                $data['auth_image'] = $image->storeAs('authors', $filename, 's3');
            } else {
                unset($data['auth_image']);
            }

            $author->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Author updated successfully',
                'data' => new AuthorResource($author)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update author',
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

            Excel::import(new AuthorImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Authors imported successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to import authors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function export()
    {
        try {
            return Excel::download(new AuthorExport, 'authors.xlsx');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export authors',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
