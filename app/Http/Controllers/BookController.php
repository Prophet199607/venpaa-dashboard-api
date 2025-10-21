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
                ->with(['authorDetails', 'category', 'subCategory', 'department', 'bookType', 'publisher', 'supplier', 'images'])
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
                ->with(['authorDetails', 'subCategory.category.department', 'bookType', 'publisher', 'supplier', 'images'])
                ->first();

            if (!$book) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book not found',
                    'error' => 'The requested book does not exist.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Book fetched successfully',
                'data' => new BookResource($book->load('images'))
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

            // Check if Book code already exists
            if (Book::where('book_code', $data['book_code'])->exists()) {
                $docCode = DocNumber::where('type', 'Book')->first()->getDocCode();
                $data['book_code'] = $docCode['code'];
            }

            $data['created_by'] = auth()->id();

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle cover image upload
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                $filename = $data['book_code'] . '.' . $coverImage->getClientOriginalExtension();
                $coverImagePath = $coverImage->storeAs('books/cover', $filename, 'public');
                $data['cover_image'] = $coverImagePath;
            } else {
                $data['cover_image'] = $data['book_code'];
            }

            $book = Book::create($data);

            // Handle multiple images upload
            if ($images) {
                foreach ($images as $image) {
                    $timestamp = now()->format('YmdHisu');
                    $filename = $book->book_code . '-' . $timestamp . '.' . $image->getClientOriginalExtension();
                    $imagePath = $image->storeAs('books/images', $filename, 'public');
                    BookImage::create([
                        'book_code' => $book->book_code,
                        'image' => $imagePath,
                        'created_by' => auth()->id()
                    ]);
                }
            }

            DB::commit();

            // Load relationships for the resource
            $book->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'authorDetails', 'images']);

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
            $data['updated_by'] = auth()->id();

            $new_book_code = $data['book_code'] ?? $book_code;

            // Separate image data from book data
            $images = $request->file('images');
            unset($data['images']);

            // Handle cover image update
            if ($request->hasFile('cover_image') || (isset($data['book_code']) && $data['book_code'] !== $book_code)) {
                if ($book->cover_image) {
                    Storage::disk('public')->delete($book->cover_image);
                }
            }

            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                $filename = $new_book_code . '.' . $coverImage->getClientOriginalExtension();
                $data['cover_image'] = $coverImage->storeAs('books/cover', $filename, 'public');
            } else {
                $data['cover_image'] = $new_book_code;
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
                    $timestamp = now()->format('YmdHisu');
                    $filename = $new_book_code . '-' . $timestamp . '.' . $imagefile->getClientOriginalExtension();
                    $path = $imagefile->storeAs('books/images', $filename, 'public');
                    BookImage::create([
                        'book_code' => $new_book_code,
                        'image' => $path,
                        'updated_by' => auth()->id(),
                    ]);
                }
            }

            $book->update($data);
            DB::commit();

            // Load relationships for the resource
            $book->load(['bookType', 'department', 'category', 'subCategory', 'publisher', 'supplier', 'authorDetails', 'images']);

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
