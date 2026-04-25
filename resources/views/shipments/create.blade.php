<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create New Shipment') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form action="{{ route('shipments.store') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="exchange_id" class="block font-medium text-sm text-gray-700">Select Accepted Exchange</label>
                                <select name="exchange_id" id="exchange_id" class="block mt-1 w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" required>
                                    <option value="">-- Select an exchange --</option>
                                    @foreach($exchanges as $exchange)
                                        <option value="{{ $exchange->id }}">
                                            {{ $exchange->offeredItem->title }} for {{ $exchange->requestedItem->title }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="courier" class="block font-medium text-sm text-gray-700">Courier</label>
                                <input type="text" name="courier" id="courier" class="block mt-1 w-full" required>
                            </div>
                            <div>
                                <label for="tracking_number" class="block font-medium text-sm text-gray-700">Tracking Number</label>
                                <input type="text" name="tracking_number" id="tracking_number" class="block mt-1 w-full" required>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Shipment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
