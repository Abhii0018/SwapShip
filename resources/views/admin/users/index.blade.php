<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Users') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    @if(session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-800">{{ session('success') }}</div>
                    @endif

                    <div class="grid grid-cols-1 gap-4">
                        @foreach($users as $user)
                            <div class="p-4 border rounded flex justify-between items-center">
                                <div>
                                    <div class="font-semibold">{{ $user->name }} @if($user->isAdmin()) <span class="text-sm text-blue-600">(Admin)</span>@endif</div>
                                    <div class="text-sm text-gray-600">{{ $user->email }}</div>
                                </div>
                                <div>
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-blue-600">View</a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6">
                        {{ $users->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
