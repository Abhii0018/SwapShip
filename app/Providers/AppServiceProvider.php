<?php

namespace App\Providers;

use App\Models\ExchangeRequest;
use App\Models\Message;
use App\Support\MailConfigurator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        MailConfigurator::apply();

        // Production safety fallback: if DB-backed cache/session tables are missing,
        // force file driver to avoid full auth-page 500 failures.
        try {
            if (config('session.driver') === 'database') {
                $sessionTable = (string) config('session.table', 'sessions');
                if (! Schema::hasTable($sessionTable)) {
                    config(['session.driver' => 'file']);
                    Log::error('Sessions table missing — using file driver. Run: php artisan migrate --force');
                }
            }

            if (config('cache.default') === 'database') {
                $cacheTable = (string) config('cache.stores.database.table', 'cache');
                if (! Schema::hasTable($cacheTable)) {
                    config(['cache.default' => 'file']);
                }
            }
        } catch (\Throwable $e) {
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
            ]);

            Log::warning('Falling back to file session/cache drivers', [
                'error' => $e->getMessage(),
            ]);
        }
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
            config(['session.secure' => true]);
        }

        View::composer('layouts.navigation', function ($view): void {
            $user = auth()->user();
            if (! $user) {
                $view->with('navNotificationCount', 0);
                $view->with('navNotificationItems', collect());
                return;
            }

            try {
                $this->composeNavigationNotifications($view, $user);
            } catch (\Throwable $exception) {
                report($exception);
                $view->with('navNotificationCount', 0);
                $view->with('navNotificationItems', collect());
            }
        });
    }

    protected function composeNavigationNotifications($view, $user): void
    {
            $cacheKey = 'nav_notifications:' . $user->id;
            $ttl = 60; // 1 minute cache

            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $view->with('navNotificationCount', $cached['count']);
                $view->with('navNotificationItems', collect($cached['items']));
                return;
            }

            $pendingRequestCount = ExchangeRequest::query()
                ->where('receiver_id', $user->id)
                ->where('status', 'Pending')
                ->count();

            // Single SQL aggregate query for total unread count instead of loading every
            // conversation row into PHP and summing.
            $unreadMessageCount = (int) Message::query()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)
                ->whereNull('deleted_for_receiver_at')
                ->whereHas('exchangeRequest', function ($q) use ($user) {
                    $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
                })
                ->count();

            $unreadConversations = ExchangeRequest::query()
                ->with(['item:id,title,user_id', 'sender:id,name', 'receiver:id,name'])
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

            // Single grouped query for previews to avoid N+1 (was: one query per conversation).
            $conversationIds = $unreadConversations->pluck('id')->all();
            $latestUnreadByConversation = collect();
            if (! empty($conversationIds)) {
                $latestUnreadByConversation = Message::query()
                    ->whereIn('exchange_request_id', $conversationIds)
                    ->whereNull('read_at')
                    ->where('sender_id', '!=', $user->id)
                    ->whereNull('deleted_for_receiver_at')
                    ->latest('id')
                    ->get()
                    ->groupBy('exchange_request_id')
                    ->map->first();
            }

            $messageItems = $unreadConversations->map(function (ExchangeRequest $conversation) use ($user, $latestUnreadByConversation) {
                $otherUser = $conversation->sender_id === $user->id ? $conversation->receiver : $conversation->sender;
                $latestUnread = $latestUnreadByConversation->get($conversation->id);
                $preview = $latestUnread?->body ?: ($latestUnread?->attachment_name ? '[Attachment]' : 'New message');

                return [
                    'type' => 'message',
                    'title' => ($otherUser?->name ?? 'User').' sent you a message',
                    'subtitle' => (string) $preview,
                    'meta' => ($conversation->unread_messages_count ?? 0).' unread',
                    'timestamp' => optional($latestUnread?->created_at ?? $conversation->updated_at)?->timestamp ?? 0,
                ];
            })->values();

            $requestItems = ExchangeRequest::query()
                ->with(['item:id,title', 'sender:id,name'])
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
                })->values();

            $notificationItems = collect($messageItems->toArray())
                ->merge($requestItems->toArray())
                ->sortByDesc('timestamp')
                ->take(6)
                ->values();

            $view->with('navNotificationCount', (int) ($pendingRequestCount + $unreadMessageCount));
            $view->with('navNotificationItems', $notificationItems);

            Cache::put($cacheKey, [
                'count' => (int) ($pendingRequestCount + $unreadMessageCount),
                'items' => $notificationItems->toArray(),
            ], $ttl);
    }
}
