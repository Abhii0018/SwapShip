<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $user->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    @if(session('success'))
                        <div class="mb-4 p-3 bg-green-100 text-green-800">{{ session('success') }}</div>
                    @endif

                    <p><strong>Email:</strong> {{ $user->email }}</p>
                    <p><strong>Role:</strong> {{ $user->role }}</p>
                    <p><strong>Banned:</strong> {{ $user->isBanned() ? 'Yes' : 'No' }}</p>

                    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-4">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="role">Role</label>
                                <select name="role" id="role" class="block mt-1 w-full">
                                    <option value="user" @if($user->role == 'user') selected @endif>User</option>
                                    <option value="moderator" @if($user->role == 'moderator') selected @endif>Moderator</option>
                                    <option value="admin" @if($user->role == 'admin') selected @endif>Admin</option>
                                </select>
                            </div>
                            <div>
                                <label for="is_banned">Banned</label>
                                <select name="is_banned" id="is_banned" class="block mt-1 w-full">
                                    <option value="0" @if(!$user->isBanned()) selected @endif>No</option>
                                    <option value="1" @if($user->isBanned()) selected @endif>Yes</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="px-4 py-2 bg-blue-600 text-white rounded">Update User</button>
                        </div>
                    </form>

                    <div class="mt-6">
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Delete user?');">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600">Delete User</button>
                        </form>
                    </div>

                    <div class="mt-6">
                        <h3 class="font-semibold">User's Items</h3>
                        <div class="grid grid-cols-1 gap-4 mt-2">
                            @foreach($user->items ?? [] as $item)
                                <div class="p-3 border rounded">
                                    <a href="{{ route('admin.items.show', $item) }}" class="text-blue-600">{{ $item->title }}</a>
                                </div>
                            @endforeach
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
