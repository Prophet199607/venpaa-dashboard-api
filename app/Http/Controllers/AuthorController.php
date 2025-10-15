<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Models\Author;
use App\Models\DocNumber;
use Illuminate\Http\Request;

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
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        try {
            $authors = Author::all();
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


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AuthorRequest $request)
    {

        try {
            $data = $request->validated();
            // Handle image upload
            if ($request->hasFile('auth_image')) {
                $imagePath = $request->file('auth_image')->store('authors', 'public');
                $data['auth_image'] = $imagePath;
            }

            $data['created_by'] = auth()->id();

            // Check if Author code already exists
            if (Author::where('auth_code', $data['auth_code'])->exists()) {
                $data['auth_code'] = DocNumber::where('type', 'Author')->first()->getDocCode();
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

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
