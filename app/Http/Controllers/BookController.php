<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\BookImage;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $books = Book::with(['images','user'])->latest()->paginate(12);

        return view('books.index', compact('books'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('books.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'condition' => 'nullable|string|max:100',
            'exchange_type' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'images.*' => 'nullable|image|max:5120',
        ]);

        $validated['user_id'] = $request->user()->id;

        $book = Book::create($validated);

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                $path = $file->store('books', 'public');
                $url = Storage::url($path);
                BookImage::create([
                    'book_id' => $book->id,
                    'url' => $url,
                    'public_id' => null,
                ]);
            }
        }

        return redirect()->route('books.index')->with('success', 'Book created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        $book->load(['images','user']);
        return view('books.show', compact('book'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book)
    {
        if ($requestUser = request()->user()) {
            if ($requestUser->id !== $book->user_id && ! $requestUser->isAdmin()) {
                abort(403);
            }
        }

        return view('books.edit', compact('book'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Book $book)
    {
        $user = $request->user();
        if ($user->id !== $book->user_id && ! $user->isAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'author' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'condition' => 'nullable|string|max:100',
            'exchange_type' => 'required|string',
            'price' => 'nullable|numeric|min:0',
            'images.*' => 'nullable|image|max:5120',
            'replace_images' => 'nullable|boolean',
        ]);

        $book->update($validated);

        if ($request->hasFile('images')) {
            if ($request->boolean('replace_images')) {
                foreach ($book->images as $img) {
                    $stored = str_replace('/storage/', '', $img->url);
                    Storage::disk('public')->delete($stored);
                    $img->delete();
                }
            }

            foreach ($request->file('images') as $file) {
                $path = $file->store('books', 'public');
                $url = Storage::url($path);
                BookImage::create([
                    'book_id' => $book->id,
                    'url' => $url,
                    'public_id' => null,
                ]);
            }
        }

        return redirect()->route('books.show', $book)->with('success', 'Book updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book)
    {
        $user = request()->user();
        if ($user->id !== $book->user_id && ! $user->isAdmin()) {
            abort(403);
        }

        foreach ($book->images as $img) {
            $stored = str_replace('/storage/', '', $img->url);
            Storage::disk('public')->delete($stored);
            $img->delete();
        }

        $book->delete();

        return redirect()->route('books.index')->with('success', 'Book deleted.');
    }
}
