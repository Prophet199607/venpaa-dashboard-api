<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\DocNumber;
use App\Models\BookImage;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\BookRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\BookResource;
use Illuminate\Support\Facades\Storage;

class BookController extends Controller
{
    public function generateBookCode()
    {
        try {
            $docCode = DocNumber::where('type', 'Book')->first()->getDocCode();

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
            $books = Book::where('status', 1)
                ->with(['author', 'category', 'subCategory', 'department', 'bookType', 'publisher', 'supplier', 'images'])
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Books fetched successfully',
                'data' => BookResource::collection($books)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch books',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($book_code)
    {
        try {
            $book = Book::where('book_code', $book_code)
                ->with(['author', 'category', 'subCategory', 'department', 'bookType', 'publisher', 'supplier', 'images'])
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Book fetched successfully',
                'data' => new BookResource($book)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function store(BookRequest $request)
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $data['created_by'] = auth()->id();

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                $coverImagePath = $coverImage->store('books/cover', 'public');
                $data['cover_image'] = $coverImagePath;
            }

            $book = Book::create($data);

            // Handle multiple images upload
            if ($images) {
                foreach ($images as $image) {
                    $imagePath = $image->store('books/images', 'public');
                    BookImage::create([
                        'book_code' => $book->book_code,
                        'image' => $imagePath,
                        'created_by' => auth()->id()
                    ]);
                }
            }

            DB::commit();

            // Load relationships for the resource
            $book->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'author', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => new BookResource($book)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create book',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(BookRequest $request, $book_code)
    {
        try {
            DB::beginTransaction();
            $book = Book::where('book_code', $book_code)->first();
            $data = $request->validated();

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle cover image update if provided
            if ($request->hasFile('cover_image')) {
                // Delete old image if exists
                if ($book->cover_image) {
                    Storage::disk('public')->delete($book->cover_image);
                }

                $imagePath = $request->file('cover_image')->store('books/cover', 'public');
                $data['cover_image'] = $imagePath;
            }

            // Handle multiple images update
            if ($images) {
                // Delete old images
                foreach ($book->images as $image) {
                    Storage::disk('public')->delete($image->image);
                    $image->delete();
                }

                // Upload new images
                foreach ($images as $imagefile) {
                    $path = $imagefile->store('books/gallery', 'public');
                    BookImage::create([
                        'book_code' => $book->book_code,
                        'image' => $path,
                        'created_by' => auth()->id(),
                    ]);
                }
            }

            $data['updated_by'] = auth()->id();
            $book->update($data);

            DB::commit();

            // Load relationships for the resource
            $book->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'author', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => new BookResource($book)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update book',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}