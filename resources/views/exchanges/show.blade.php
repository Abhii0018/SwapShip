<x-app-layout>
    <section class="card">
        <h2>Exchange #{{ $exchangeRequest->id }}</h2>
        <p><strong>Item:</strong> {{ $exchangeRequest->item->title }}</p>
        <p><strong>Sender:</strong> {{ $exchangeRequest->sender->name }}</p>
        <p><strong>Receiver:</strong> {{ $exchangeRequest->receiver->name }}</p>
        <p><strong>Status:</strong> {{ $exchangeRequest->status }}</p>
        <a class="btn btn-primary" href="{{ route('chat.index', $exchangeRequest) }}">Open Chat</a>
    </section>
</x-app-layout>
