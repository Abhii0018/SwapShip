<?php

namespace App\Providers;

use App\Models\ExchangeRequest;
use App\Models\Message;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url');

        // Cloudflare tunnel terminates TLS before forwarding to local HTTP.
        // Force HTTPS, but keep host dynamic from the incoming request.
        if ($appUrl !== '' && str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        View::composer('layouts.navigation', function ($view): void {
            $user = auth()->user();
            if (! $user) {
                $view->with('navNotificationCount', 0);
                $view->with('navNotificationItems', collect());
                return;
            }

            $pendingRequestCount = ExchangeRequest::query()
                ->where('receiver_id', $user->id)
                ->where('status', 'Pending')
                ->count();

            $unreadMessageCount = ExchangeRequest::query()
                ->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
                })
                ->withCount(['messages as unread_messages_count' => function ($q) use ($user) {
                    $q->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('deleted_for_receiver_at');
                }])
                ->get()
                ->sum('unread_messages_count');

            $unreadConversations = ExchangeRequest::query()
                ->with(['item', 'sender', 'receiver'])
                ->where(function ($q) use ($user) {
                    $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
                })
                ->whereHas('messages', function ($q) use ($user) {
                    $q->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('deleted_for_receiver_at');
                })
                ->withCount(['messages as unread_messages_count' => function ($q) use ($user) {
                    $q->whereNull('read_at')
                        ->where('sender_id', '!=', $user->id)
                        ->whereNull('deleted_for_receiver_at');
                }])
                ->latest('updated_at')
                ->limit(5)
                ->get();

            $messageItems = $unreadConversations->map(function (ExchangeRequest $conversation) use ($user) {
                $otherUser = $conversation->sender_id === $user->id ? $conversation->receiver : $conversation->sender;
                $latestUnread = Message::query()
                    ->where('exchange_request_id', $conversation->id)
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('deleted_for_receiver_at')
                    ->latest()
                    ->first();

                $preview = $latestUnread?->body ?: ($latestUnread?->attachment_name ? '[Attachment]' : 'New message');

                return [
                    'type' => 'message',
                    'title' => ($otherUser?->name ?? 'User').' sent you a message',
                    'subtitle' => (string) $preview,
                    'meta' => ($conversation->unread_messages_count ?? 0).' unread',
                    'timestamp' => optional($latestUnread?->created_at ?? $conversation->updated_at)?->timestamp ?? 0,
                ];
            });

            $requestItems = ExchangeRequest::query()
                ->with(['item', 'sender'])
                ->where('receiver_id', $user->id)
                ->where('status', 'Pending')
                ->latest()
                ->limit(5)
                ->get()
                ->map(function (ExchangeRequest $request) {
                    return [
                        'type' => 'request',
                        'title' => ($request->sender?->name ?? 'User').' sent an exchange request',
                        'subtitle' => 'Item: '.($request->item?->title ?? 'Listing'),
                        'meta' => 'Pending',
                        'timestamp' => optional($request->created_at)?->timestamp ?? 0,
                    ];
                });

            $notificationItems = collect($messageItems)
                ->merge($requestItems)
                ->sortByDesc('timestamp')
                ->take(6)
                ->values();

            $view->with('navNotificationCount', (int) ($pendingRequestCount + $unreadMessageCount));
            $view->with('navNotificationItems', $notificationItems);
        });
    }
}
