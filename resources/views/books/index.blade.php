<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Books') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-center">
                <div class="text-lg font-bold">Books</div>
                <a href="{{ route('books.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Add Book</a>
            </div>

            @if(session('success'))
                <div class="mb-4 p-3 bg-green-100 text-green-800">{{ session('success') }}</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @forelse($items ?? $books ?? [] as $item)
                    <div class="bg-white shadow rounded overflow-hidden">
                        <a href="{{ route((isset($item->title) ? 'books.show' : 'items.show'), $item) }}">
                            <img src="{{ $item->images->first()->url ?? asset('images/placeholder.png') }}" alt="{{ $item->title }}" class="w-full h-48 object-cover">
                            <div class="p-4">
                                <h3 class="font-semibold text-lg">{{ $item->title }}</h3>
                                <p class="text-sm text-gray-500">{{ $item->author }}</p>
                            </div>
                        </a>
                        <div class="p-4 border-t flex justify-between items-center">
                            <span class="text-sm text-gray-600">{{ $item->exchange_type }}</span>
                            @if(auth()->check() && (auth()->id() === $item->user_id || auth()->user()->isAdmin()))
                                <div class="flex items-center gap-2">
                                    <a href="{{ route((isset($item->title) ? 'books.edit' : 'items.edit'), $item) }}" class="text-blue-600">Edit</a>
                                    <form action="{{ route((isset($item->title) ? 'books.destroy' : 'items.destroy'), $item) }}" method="POST" onsubmit="return confirm('Delete this item?');">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600">Delete</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center text-gray-500">No items found.</div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $books->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
