<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $item->title }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if(session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-800">{{ session('success') }}</div>
                    @endif

                    <p><strong>Owner:</strong> {{ $item->user->name }}</p>
                    <p><strong>Category:</strong> {{ $item->category }}</p>
                    <p><strong>Condition:</strong> {{ $item->condition }}</p>
                    <p><strong>Approved:</strong> {{ $item->approved ? 'Yes' : 'No' }}</p>

                    <form method="POST" action="{{ route('admin.items.update', $item) }}" class="mt-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="approved">Approved</label>
                                <select name="approved" id="approved" class="block mt-1 w-full">
                                    <option value="1" @if($item->approved) selected @endif>Yes</option>
                                    <option value="0" @if(!$item->approved) selected @endif>No</option>
                                </select>
                            </div>
                            <div>
                                <label for="locked">Locked</label>
                                <select name="locked" id="locked" class="block mt-1 w-full">
                                    <option value="0" @if(!$item->locked) selected @endif>No</option>
                                    <option value="1" @if($item->locked) selected @endif>Yes</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="px-4 py-2 bg-blue-600 text-white rounded">Update Item</button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <form action="{{ route('admin.items.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete item?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600">Delete Item</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
