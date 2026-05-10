<?php

namespace App\Http\Controllers;

use App\Models\ExchangeRequest;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request, ?ExchangeRequest $exchangeRequest = null): View
    {
        $user = $request->user();
        if (! $user && $request->session()->has('actor_user_id')) {
            $user = User::query()->find((int) $request->session()->get('actor_user_id'));
        }
        if (! $user) {
            return view('messages.index', [
                'conversations' => collect(),
                'activeConversation' => null,
                'messages' => collect(),
                'systemEvents' => collect(),
                'blocked' => false,
            ]);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        $conversations = ExchangeRequest::query()
            ->with(['item', 'sender', 'receiver', 'shipment.events', 'messages' => fn ($q) => $q->latest()->limit(1)])
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->whereNull('read_at')->where('sender_id', '!=', $user->id);
            }])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->orderByDesc(
                Message::query()
                    ->select('created_at')
                    ->whereColumn('exchange_request_id', 'exchange_requests.id')
                    ->latest()
                    ->limit(1)
            )
            ->orderByDesc('created_at')
            ->get();

        $activeConversation = $exchangeRequest;
        if (! $activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
        }
        if ($activeConversation && ! in_array($user->id, [$activeConversation->sender_id, $activeConversation->receiver_id], true)) {
            abort(403);
        }

        $messages = collect();
        $messagePage = null;
        $systemEvents = collect();
        $blocked = false;
        if ($activeConversation) {
            $activeConversation->load(['item', 'sender', 'receiver', 'shipment.events']);
            $messagePage = $activeConversation->messages()
                ->with('sender')
                ->where(function ($q) use ($user) {
                    $q->where(function ($inner) use ($user) {
                        $inner->where('sender_id', $user->id)
                            ->whereNull('deleted_for_sender_at');
                    })->orWhere(function ($inner) use ($user) {
                        $inner->where('sender_id', '!=', $user->id)
                            ->whereNull('deleted_for_receiver_at');
                    });
                })
                ->latest()
                ->paginate(30)
                ->withQueryString();
            $messages = $messagePage->getCollection()->reverse()->values();
            $activeConversation->messages()
                ->whereNull('read_at')
                ->where('sender_id', '!=', $user->id)
                ->whereNull('deleted_for_receiver_at')
                ->update(['read_at' => now()]);
            $systemEvents = $this->buildSystemEvents($activeConversation);
            $blocked = $this->isBlocked($activeConversation->id);
        }

        $actorId = $user?->id;
        $contactReady = true;
        if ($activeConversation) {
            $sender = $activeConversation->sender;
            $receiver = $activeConversation->receiver;
            $contactReady = $sender && $receiver && filled($sender->phone) && filled($sender->address) && filled($receiver->phone) && filled($receiver->address);
        }

        return view('messages.index', compact('conversations', 'activeConversation', 'messages', 'messagePage', 'systemEvents', 'blocked', 'actorId', 'contactReady'));
    }

    public function store(Request $request, ExchangeRequest $exchangeRequest)
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );
        if ($this->isBlocked($exchangeRequest->id)) {
            return back()->with('error', 'Messaging is blocked for this conversation.');
        }

        $validated = $request->validate([
            'body' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,txt,webm,mp3,wav,m4a,ogg',
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $stored = $file->store('chat-attachments', 'public');
            $attachmentPath = $stored ?: null;
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
        }

        if ($body === '' && ! $attachmentPath) {
            return back()->with('error', 'Type a message or attach a file.');
        }

        $message = Message::create([
            'exchange_request_id' => $exchangeRequest->id,
            'sender_id' => $request->user()->id,
            'body' => $body,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);

        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        if ($exchangeRequest->status === 'Accepted') {
            $exchangeRequest->update(['status' => 'In Progress']);
        }

        return redirect()->route('chat.index', $exchangeRequest)->with('success', 'Message sent.');
    }

    public function typing(Request $request, ExchangeRequest $exchangeRequest): JsonResponse
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $key = "chat_typing:{$exchangeRequest->id}:{$request->user()->id}";
        Cache::put($key, now()->timestamp, now()->addSeconds(8));
        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json(['ok' => true]);
    }

    public function presence(Request $request, ExchangeRequest $exchangeRequest): JsonResponse
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        $participantIds = [$exchangeRequest->sender_id, $exchangeRequest->receiver_id];
        $typingBy = [];
        foreach ($participantIds as $participantId) {
            if ((int) $participantId === (int) $request->user()->id) {
                continue;
            }
            $key = "chat_typing:{$exchangeRequest->id}:{$participantId}";
            if (Cache::has($key)) {
                $typingBy[] = (int) $participantId;
            }
        }

        $users = User::query()
            ->whereIn('id', $participantIds)
            ->get(['id', 'last_seen_at'])
            ->mapWithKeys(fn (User $user) => [
                (string) $user->id => [
                    'last_seen_at' => optional($user->last_seen_at)?->toIso8601String(),
                    'last_seen_human' => $user->last_seen_at ? $user->last_seen_at->diffForHumans() : null,
                ],
            ]);

        return response()->json([
            'typing_by' => $typingBy,
            'presence' => $users,
        ]);
    }

    public function updates(Request $request, ExchangeRequest $exchangeRequest): JsonResponse
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $afterId = max(0, (int) $request->query('after_id', 0));
        $requestUserId = (int) $request->user()->id;

        $messages = $exchangeRequest->messages()
            ->with('sender')
            ->where('id', '>', $afterId)
            ->where(function ($q) use ($requestUserId) {
                $q->where(function ($inner) use ($requestUserId) {
                    $inner->where('sender_id', $requestUserId)
                        ->whereNull('deleted_for_sender_at');
                })->orWhere(function ($inner) use ($requestUserId) {
                    $inner->where('sender_id', '!=', $requestUserId)
                        ->whereNull('deleted_for_receiver_at');
                });
            })
            ->oldest()
            ->get();

        $exchangeRequest->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $requestUserId)
            ->update(['read_at' => now()]);

        // Highest message id sent by current user that has now been read by other side.
        $readUptoId = (int) ($exchangeRequest->messages()
            ->where('sender_id', $requestUserId)
            ->whereNotNull('read_at')
            ->max('id') ?? 0);

        $request->user()->forceFill(['last_seen_at' => now()])->saveQuietly();

        $payload = $messages->map(function (Message $message) {
            $isImage = str_starts_with((string) $message->attachment_mime, 'image/');
            $isAudio = str_starts_with((string) $message->attachment_mime, 'audio/');
            $dateKey = optional($message->created_at)->format('Y-m-d');
            $dateLabel = optional($message->created_at)?->isToday()
                ? 'Today'
                : (optional($message->created_at)?->isYesterday() ? 'Yesterday' : optional($message->created_at)?->format('d M Y'));
            return [
                'id' => (int) $message->id,
                'sender_id' => (int) $message->sender_id,
                'sender_name' => (string) ($message->sender?->name ?? 'User'),
                'body' => (string) ($message->body ?? ''),
                'attachment_url' => $message->attachmentUrl(),
                'attachment_name' => $message->attachment_name,
                'attachment_is_image' => $isImage,
                'attachment_is_audio' => $isAudio,
                'created_label' => $message->created_at?->format('d M, h:i A'),
                'date_key' => $dateKey,
                'date_label' => $dateLabel,
                'is_read' => (bool) $message->read_at,
            ];
        })->values();

        return response()->json([
            'messages' => $payload,
            'read_upto_id' => $readUptoId,
        ]);
    }

    public function notificationSummary(Request $request): JsonResponse
    {
        abort_unless($request->user(), 403);
        $user = $request->user();

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
        })->values();

        $requestItems = ExchangeRequest::query()
            ->with(['item', 'sender'])
            ->where('receiver_id', $user->id)
            ->where('status', 'Pending')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (ExchangeRequest $exchangeRequest) {
                return [
                    'type' => 'request',
                    'title' => ($exchangeRequest->sender?->name ?? 'User').' sent an exchange request',
                    'subtitle' => 'Item: '.($exchangeRequest->item?->title ?? 'Listing'),
                    'meta' => 'Pending',
                    'timestamp' => optional($exchangeRequest->created_at)?->timestamp ?? 0,
                ];
            })->values();

        $notificationItems = collect($messageItems->toArray())
            ->merge($requestItems->toArray())
            ->sortByDesc('timestamp')
            ->take(6)
            ->values();

        return response()->json([
            'count' => (int) ($pendingRequestCount + $unreadMessageCount),
            'items' => $notificationItems,
        ]);
    }

    public function report(Request $request, ExchangeRequest $exchangeRequest)
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $reports = Cache::get('chat_reports', []);
        $reports[] = [
            'exchange_request_id' => $exchangeRequest->id,
            'reported_by' => $request->user()->id,
            'created_at' => now()->toDateTimeString(),
        ];
        Cache::forever('chat_reports', $reports);

        return back()->with('success', 'User reported. Our team will review this conversation.');
    }

    public function delete(Request $request, ExchangeRequest $exchangeRequest, Message $message)
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );
        abort_unless((int) $message->exchange_request_id === (int) $exchangeRequest->id, 404);

        $validated = $request->validate([
            'scope' => 'required|in:me,everyone',
        ]);

        $userId = (int) $request->user()->id;
        if ($validated['scope'] === 'everyone') {
            if ((int) $message->sender_id !== $userId) {
                if ($request->expectsJson()) {
                    return response()->json(['ok' => false, 'message' => 'Only sender can delete for everyone.'], 422);
                }
                return back()->with('error', 'Only sender can delete for everyone.');
            }
            $oldAttachmentPath = $message->attachment_path;
            $message->forceFill([
                'body' => '[deleted]',
                'attachment_path' => null,
                'attachment_name' => null,
                'attachment_mime' => null,
                'attachment_size' => null,
                'deleted_for_sender_at' => now(),
                'deleted_for_receiver_at' => now(),
            ])->save();
            if ($oldAttachmentPath) {
                Storage::disk('public')->delete($oldAttachmentPath);
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message_id' => (int) $message->id,
                    'scope' => 'everyone',
                ]);
            }
            return back()->with('success', 'Message deleted for everyone.');
        }

        if ((int) $message->sender_id === $userId) {
            $message->forceFill(['deleted_for_sender_at' => now()])->save();
        } else {
            $message->forceFill(['deleted_for_receiver_at' => now()])->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message_id' => (int) $message->id,
                'scope' => 'me',
            ]);
        }

        return back()->with('success', 'Message deleted for you.');
    }

    public function block(Request $request, ExchangeRequest $exchangeRequest)
    {
        abort_unless($request->user(), 403);
        abort_unless(
            in_array($request->user()->id, [$exchangeRequest->sender_id, $exchangeRequest->receiver_id], true),
            403
        );

        $blocked = Cache::get('chat_blocks', []);
        $blocked[$exchangeRequest->id] = true;
        Cache::forever('chat_blocks', $blocked);

        return back()->with('success', 'This conversation is blocked. Messages are now disabled.');
    }

    protected function isBlocked(int $exchangeRequestId): bool
    {
        $blocked = Cache::get('chat_blocks', []);

        return (bool) ($blocked[$exchangeRequestId] ?? false);
    }

    protected function buildSystemEvents(ExchangeRequest $exchangeRequest)
    {
        $events = collect([
            [
                'text' => 'Request sent',
                'time' => optional($exchangeRequest->created_at)->format('d M, h:i A'),
                'ts' => optional($exchangeRequest->created_at)?->timestamp ?? 0,
            ],
        ]);

        if (in_array($exchangeRequest->status, ['Accepted', 'In Progress', 'Completed'], true)) {
            $events->push([
                'text' => 'Request accepted',
                'time' => optional($exchangeRequest->updated_at)->format('d M, h:i A'),
                'ts' => optional($exchangeRequest->updated_at)?->timestamp ?? 0,
            ]);
        }
        if ($exchangeRequest->shipment) {
            $events->push([
                'text' => 'Shipment created',
                'time' => optional($exchangeRequest->shipment->created_at)->format('d M, h:i A'),
                'ts' => optional($exchangeRequest->shipment->created_at)?->timestamp ?? 0,
            ]);
            foreach ($exchangeRequest->shipment->events as $event) {
                $events->push([
                    'text' => 'Shipment: '.$event->event_label,
                    'time' => optional($event->occurred_at ?? $event->created_at)->format('d M, h:i A'),
                    'ts' => optional($event->occurred_at ?? $event->created_at)?->timestamp ?? 0,
                ]);
            }
        }
        if ($exchangeRequest->status === 'Completed') {
            $events->push([
                'text' => 'Delivered successfully',
                'time' => optional($exchangeRequest->updated_at)->format('d M, h:i A'),
                'ts' => optional($exchangeRequest->updated_at)?->timestamp ?? 0,
            ]);
        }

        return $events->sortBy('ts')->values();
    }
}
