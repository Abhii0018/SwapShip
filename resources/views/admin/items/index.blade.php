<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Items Moderation') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if(session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-800">{{ session('success') }}</div>
                    @endif

                    <div class="grid grid-cols-1 gap-4">
                        @foreach($items as $item)
                            <div class="p-4 border rounded flex justify-between items-center">
                                <div>
                                    <div class="font-semibold">{{ $item->title }}</div>
                                    <div class="text-sm text-gray-600">Owner: {{ $item->user->name }}</div>
                                    <div class="text-sm text-gray-600">Approved: {{ $item->approved ? 'Yes' : 'No' }}</div>
                                </div>
                                <div>
                                    <a href="{{ route('admin.items.show', $item) }}" class="text-blue-600">View</a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $items->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
