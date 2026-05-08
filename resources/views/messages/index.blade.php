<x-app-layout>
    <section class="chat-shell">
        <aside class="card chat-sidebar">
            @forelse($conversations as $conversation)
                @php
                    $otherUser = (($actorId ?? auth()->id()) === $conversation->sender_id) ? $conversation->receiver : $conversation->sender;
                    $lastMessage = $conversation->messages->first();
                @endphp
                <a href="{{ route('chat.index', $conversation) }}" class="chat-thread {{ optional($activeConversation)->id === $conversation->id ? 'is-active' : '' }}">
                    <div class="chat-thread-top">
                        <div class="chat-thread-user">
                            <span class="chat-avatar">{{ $otherUser?->initials() ?? 'U' }}</span>
                            <strong>{{ $otherUser?->name ?? 'User' }}</strong>
                        </div>
                        <span>{{ optional(optional($lastMessage)->created_at)->diffForHumans() }}</span>
                    </div>
                    <p class="chat-thread-item">{{ $conversation->item?->title ?? 'Item removed' }}</p>
                    <p class="chat-thread-preview">{{ $lastMessage?->body ?? 'No messages yet' }}</p>
                    @if($conversation->unread_count > 0)
                        <span class="chat-unread">{{ $conversation->unread_count }}</span>
                    @endif
                </a>
            @empty
                <div class="chat-empty card">
                    <h3>No conversations yet</h3>
                    <p class="muted">Explore items and send an exchange request to start chatting.</p>
                    <a class="btn" href="{{ route('items.index') }}">Explore Items</a>
                </div>
            @endforelse
        </aside>

        <main class="card chat-main">
            @if(!$activeConversation)
                <div class="chat-empty">
                    <h3>No conversations yet</h3>
                    <p class="muted">Start from Explore, request an exchange, and your chat will appear here.</p>
                    <a class="btn btn-primary" href="{{ route('items.index') }}">Go to Explore</a>
                </div>
            @else
                @php
                    $otherUser = (($actorId ?? auth()->id()) === $activeConversation->sender_id) ? $activeConversation->receiver : $activeConversation->sender;
                    $canModerate = ($actorId ?? auth()->id()) === $activeConversation->receiver_id;
                    $isSender = ($actorId ?? auth()->id()) === $activeConversation->sender_id;
                    $otherUserAge = filled($otherUser?->age) ? $otherUser->age : 'Not added';
                    $otherUserAddress = filled($otherUser?->address) ? $otherUser->address : 'Not added';
                    $otherUserCity = filled($otherUser?->city) ? $otherUser->city : 'Not added';
                    $otherUserLocation = filled($otherUser?->location) ? $otherUser->location : 'Not added';
                @endphp
                <header class="chat-context">
                    <div>
                        <h2 class="chat-context-title">
                            <span class="chat-avatar">{{ $otherUser?->initials() ?? 'U' }}</span>
                            <span>{{ $otherUser?->name ?? 'User' }}</span>
                        </h2>
                        <p class="chat-context-sub">Item: {{ $activeConversation->item?->title ?? 'Item removed' }}</p>
                        <p class="chat-context-presence" id="chat-presence-status" data-other-user-id="{{ $otherUser?->id }}" data-other-user-name="{{ $otherUser?->firstName() ?? ($otherUser?->name ?? 'User') }}">
                            Last online: {{ optional($otherUser?->last_seen_at)->diffForHumans() ?? 'Unknown' }}
                        </p>
                        <details class="chat-profile-details">
                            <summary>View profile details</summary>
                            <div class="chat-profile-card">
                                <span><strong>Name:</strong> {{ $otherUser?->name ?? 'User' }}</span>
                                <span><strong>Age:</strong> {{ $otherUserAge }}</span>
                                <span><strong>City:</strong> {{ $otherUserCity }}</span>
                                <span><strong>Location:</strong> {{ $otherUserLocation }}</span>
                                <span><strong>Address:</strong> {{ $otherUserAddress }}</span>
                            </div>
                        </details>
                        <div class="chat-confirmation-progress">
                            <div class="chat-confirm-pill {{ $activeConversation->sender_confirmed_at ? 'is-done' : 'is-pending' }}">
                                <strong>Sender</strong>
                                @if($activeConversation->sender_confirmed_at)
                                    <span>Confirmed · {{ $activeConversation->sender_confirmed_at->format('d M, h:i A') }}</span>
                                @else
                                    <span>Pending confirmation</span>
                                @endif
                            </div>
                            <div class="chat-confirm-pill {{ $activeConversation->receiver_confirmed_at ? 'is-done' : 'is-pending' }}">
                                <strong>Receiver</strong>
                                @if($activeConversation->receiver_confirmed_at)
                                    <span>Confirmed · {{ $activeConversation->receiver_confirmed_at->format('d M, h:i A') }}</span>
                                @else
                                    <span>Pending confirmation</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="chat-context-right">
                        <span class="chat-status-pill">{{ $activeConversation->status }}</span>
                        <div class="chat-safety">
                            @auth
                                <form method="POST" action="{{ route('chat.report', $activeConversation) }}">
                                    @csrf
                                    <button class="btn" type="submit">Report user</button>
                                </form>
                                <form method="POST" action="{{ route('chat.block', $activeConversation) }}">
                                    @csrf
                                    <button class="btn" type="submit">Block user</button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </header>

                <section class="chat-actions">
                    @if(!$contactReady && auth()->check())
                        <a class="btn" href="{{ route('profile.edit') }}">Complete phone/address to continue</a>
                    @endif
                    @if($canModerate && $activeConversation->status === 'Pending')
                        <form method="POST" action="{{ route('exchanges.update-status', $activeConversation) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="Accepted">
                            <button type="submit" class="btn btn-primary">Accept</button>
                        </form>
                        <form method="POST" action="{{ route('exchanges.update-status', $activeConversation) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="Rejected">
                            <button type="submit" class="btn">Reject</button>
                        </form>
                    @elseif($activeConversation->status === 'Accepted' && $contactReady && $isSender && !$activeConversation->sender_confirmed_at)
                        <form method="POST" action="{{ route('exchanges.confirm', $activeConversation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-primary">Confirm Exchange</button>
                        </form>
                    @elseif($activeConversation->status === 'Accepted' && !$isSender && !$activeConversation->receiver_confirmed_at)
                        <form method="POST" action="{{ route('exchanges.confirm', $activeConversation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-primary">Confirm Exchange</button>
                        </form>
                    @elseif(in_array($activeConversation->status, ['Accepted', 'In Progress'], true))
                        @if(!$isSender)
                            <a class="btn btn-primary" href="{{ route('exchanges.deal-terms', $activeConversation) }}">Set deal terms</a>
                        @else
                            <a class="btn btn-primary" href="{{ route('exchanges.deal-terms', $activeConversation) }}">View deal &amp; pay</a>
                        @endif
                        <a class="btn" href="{{ route('shipments.index') }}">Proceed to shipment</a>
                    @elseif($activeConversation->status === 'Completed')
                        <a class="btn" href="{{ route('shipments.index') }}">View shipment status</a>
                    @endif
                </section>

                @if($activeConversation->status === 'Pending')
                    <section class="chat-system-events">
                        @foreach($systemEvents as $event)
                            <div class="chat-system-line">
                                <strong>{{ $event['text'] }}</strong>
                                <span>{{ $event['time'] }}</span>
                            </div>
                        @endforeach
                    </section>
                @endif

                @php($lastDateKey = null)
                <section class="chat-window" id="chat-window" data-last-message-id="{{ (int) ($messages->last()->id ?? 0) }}" data-last-date-key="{{ optional($messages->last()?->created_at)->format('Y-m-d') }}">
                    @if($messagePage && $messagePage->hasMorePages())
                        <div class="chat-load-more">
                            <a class="btn" href="{{ $messagePage->nextPageUrl() }}">Load older messages</a>
                        </div>
                    @endif
                    @forelse($messages as $message)
                        @php($messageDateKey = optional($message->created_at)->format('Y-m-d'))
                        @if($messageDateKey !== $lastDateKey)
                            <div class="chat-date-chip">
                                {{ $message->created_at?->isToday() ? 'Today' : ($message->created_at?->isYesterday() ? 'Yesterday' : $message->created_at?->format('d M Y')) }}
                            </div>
                            @php($lastDateKey = $messageDateKey)
                        @endif
                        <article class="chat-bubble {{ $message->sender_id === auth()->id() ? 'is-self' : '' }}" data-message-id="{{ $message->id }}">
                            @if(filled($message->body))
                                <p>{{ $message->body }}</p>
                            @endif
                            @if($message->attachment_path)
                                @php($isImage = str_starts_with((string) $message->attachment_mime, 'image/'))
                                @php($isAudio = str_starts_with((string) $message->attachment_mime, 'audio/'))
                                <div class="chat-attachment">
                                    @if($isImage)
                                        <a href="{{ $message->attachmentUrl() }}" target="_blank" rel="noopener" class="chat-image-open" data-image-src="{{ $message->attachmentUrl() }}">
                                            <img src="{{ $message->attachmentUrl() }}" alt="{{ $message->attachment_name ?? 'Attachment' }}">
                                        </a>
                                    @elseif($isAudio)
                                        <div class="chat-audio-msg" data-audio-src="{{ $message->attachmentUrl() }}">
                                            <button type="button" class="chat-audio-play" aria-label="Play audio">▶</button>
                                            <input type="range" class="chat-audio-progress" value="0" min="0" max="100" step="1" aria-label="Audio progress">
                                            <span class="chat-audio-time">0:00</span>
                                            <audio preload="metadata" src="{{ $message->attachmentUrl() }}"></audio>
                                        </div>
                                    @else
                                        <a href="{{ $message->attachmentUrl() }}" target="_blank" rel="noopener">
                                            [FILE] {{ $message->attachment_name ?? 'Download file' }}
                                        </a>
                                    @endif
                                </div>
                            @endif
                            <small>
                                {{ $message->created_at->format('h:i A') }}
                                <span class="chat-ticks {{ ($message->sender_id === auth()->id() && $message->read_at) || $message->sender_id !== auth()->id() ? 'is-read' : '' }}">{{ $message->sender_id === auth()->id() ? ($message->read_at ? '✓✓' : '✓') : '✓✓' }}</span>
                            </small>
                            <details class="chat-msg-actions">
                                <summary>⋮</summary>
                                <div class="chat-msg-actions-menu">
                                    <form method="POST" action="{{ route('chat.message.delete', [$activeConversation, $message]) }}" class="js-chat-delete-form" data-message-id="{{ $message->id }}">
                                        @csrf
                                        <input type="hidden" name="scope" value="me">
                                        <button type="submit">Delete for me</button>
                                    </form>
                                    @if($message->sender_id === auth()->id())
                                        <form method="POST" action="{{ route('chat.message.delete', [$activeConversation, $message]) }}" class="js-chat-delete-form" data-message-id="{{ $message->id }}">
                                            @csrf
                                            <input type="hidden" name="scope" value="everyone">
                                            <button type="submit">Delete for everyone</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        </article>
                    @empty
                        <p class="muted">No messages yet. Send the first message to start coordinating.</p>
                    @endforelse
                </section>

                @if($blocked)
                    <p class="chat-blocked-note">This conversation is blocked. Messaging is disabled.</p>
                @else
                    <p class="chat-typing-indicator" id="chat-typing-indicator" style="display:none;">Typing...</p>
                    <form method="POST" action="{{ route('chat.store', $activeConversation) }}" class="chat-input" enctype="multipart/form-data">
                        @csrf
                        <button class="btn chat-voice-btn" type="button" aria-label="Record audio" id="chat-audio-record-btn" title="Record audio">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M12 15a3.5 3.5 0 0 0 3.5-3.5V6a3.5 3.5 0 1 0-7 0v5.5A3.5 3.5 0 0 0 12 15Zm6-3.5a1 1 0 1 0-2 0A4 4 0 0 1 8 11.5a1 1 0 1 0-2 0 6 6 0 0 0 5 5.91V20H9a1 1 0 1 0 0 2h6a1 1 0 1 0 0-2h-2v-2.59A6 6 0 0 0 18 11.5Z"/>
                            </svg>
                        </button>
                        <input name="body" id="chat-message-input" placeholder="Type your message..." maxlength="1000">
                        <input type="file" name="attachment" id="chat-attachment-input" class="chat-attachment-input" accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt,.webm,.mp3,.wav,.m4a,.ogg">
                        <label for="chat-attachment-input" class="btn chat-attach-btn" aria-label="Attach file" title="Attach file">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M16.5 6.5 9 14a3 3 0 1 0 4.24 4.24l7.07-7.07a5 5 0 0 0-7.07-7.07L5.4 11.93a7 7 0 0 0 9.9 9.9l6.36-6.36a1 1 0 1 0-1.42-1.42l-6.36 6.36a5 5 0 0 1-7.07-7.07l7.84-7.83a3 3 0 1 1 4.24 4.24l-7.07 7.07a1 1 0 1 1-1.41-1.42l7.5-7.5a1 1 0 1 0-1.42-1.41Z"/>
                            </svg>
                        </label>
                        <button class="btn btn-primary" type="submit">Send</button>
                    </form>
                @endif
            @endif
        </main>
    </section>
</x-app-layout>

<div class="chat-image-viewer" id="chat-image-viewer" style="display:none;">
    <button type="button" class="chat-image-viewer-close" id="chat-image-viewer-close">Close</button>
    <img src="" alt="Full image" id="chat-image-viewer-img">
</div>

@if($activeConversation && auth()->check())
    <script>
        (() => {
            const conversationId = @json($activeConversation->id);
            const myUserId = @json((int) auth()->id());
            const typingUrl = @json(route('chat.typing', $activeConversation));
            const presenceUrl = @json(route('chat.presence', $activeConversation));
            const updatesUrl = @json(route('chat.updates', $activeConversation));
            const token = @json(csrf_token());
            const deleteMessageUrlTemplate = @json(route('chat.message.delete', [$activeConversation, '__MSG__']));
            const input = document.getElementById('chat-message-input');
            const typingIndicator = document.getElementById('chat-typing-indicator');
            const presenceStatus = document.getElementById('chat-presence-status');
            const chatWindow = document.getElementById('chat-window');
            const attachmentInput = document.getElementById('chat-attachment-input');
            const recordBtn = document.getElementById('chat-audio-record-btn');
            const otherUserId = Number(presenceStatus?.dataset.otherUserId || 0);
            const otherUserName = String(presenceStatus?.dataset.otherUserName || 'User');
            let typingTimer = null;
            let updateTimer = null;
            let mediaRecorder = null;
            let recordingChunks = [];
            let stream = null;
            let recordingMimeType = 'audio/webm';

            const sendTyping = () => {
                fetch(typingUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }).catch(() => {});
            };

            if (input) {
                input.addEventListener('input', () => {
                    if (typingTimer) clearTimeout(typingTimer);
                    sendTyping();
                    typingTimer = setTimeout(() => {}, 1800);
                });
            }

            const refreshPresence = () => {
                fetch(presenceUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then((res) => res.ok ? res.json() : null)
                .then((data) => {
                    if (!data) return;
                    const typingBy = Array.isArray(data.typing_by) ? data.typing_by : [];
                    const isTyping = typingBy.includes(otherUserId);
                    if (typingIndicator) {
                        typingIndicator.style.display = isTyping ? 'block' : 'none';
                        typingIndicator.textContent = isTyping ? `${otherUserName} is typing...` : '';
                    }
                    const presence = data.presence?.[String(otherUserId)];
                    if (presenceStatus && presence?.last_seen_human) {
                        presenceStatus.textContent = `Last online: ${presence.last_seen_human}`;
                    }
                })
                .catch(() => {});
            };

            const appendMessage = (message) => {
                if (!chatWindow || !message || !message.id) return;
                if (chatWindow.querySelector(`[data-message-id="${message.id}"]`)) return;
                const isSelf = Number(message.sender_id) === Number(myUserId);
                const incomingDateKey = String(message.date_key || '');
                const lastDateKey = String(chatWindow.dataset.lastDateKey || '');
                if (incomingDateKey && incomingDateKey !== lastDateKey) {
                    const chip = document.createElement('div');
                    chip.className = 'chat-date-chip';
                    chip.textContent = message.date_label || incomingDateKey;
                    chatWindow.appendChild(chip);
                    chatWindow.dataset.lastDateKey = incomingDateKey;
                }
                const article = document.createElement('article');
                article.className = `chat-bubble${isSelf ? ' is-self' : ''}`;
                article.setAttribute('data-message-id', String(message.id));

                if (message.body && String(message.body).trim() !== '') {
                    const p = document.createElement('p');
                    p.textContent = message.body;
                    article.appendChild(p);
                }

                if (message.attachment_url) {
                    const wrap = document.createElement('div');
                    wrap.className = 'chat-attachment';
                    if (message.attachment_is_image) {
                        const a = document.createElement('a');
                        a.href = message.attachment_url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.className = 'chat-image-open';
                        a.setAttribute('data-image-src', message.attachment_url);
                        const img = document.createElement('img');
                        img.src = message.attachment_url;
                        img.alt = message.attachment_name || 'Attachment';
                        a.appendChild(img);
                        wrap.appendChild(a);
                    } else if (message.attachment_is_audio || (message.attachment_name || '').match(/\.(webm|mp3|wav|m4a|ogg)$/i)) {
                        const player = document.createElement('div');
                        player.className = 'chat-audio-msg';
                        player.setAttribute('data-audio-src', message.attachment_url);
                        player.innerHTML = `
                            <button type="button" class="chat-audio-play" aria-label="Play audio">▶</button>
                            <input type="range" class="chat-audio-progress" value="0" min="0" max="100" step="1" aria-label="Audio progress">
                            <span class="chat-audio-time">0:00</span>
                            <audio preload="metadata" src="${message.attachment_url}"></audio>
                        `;
                        wrap.appendChild(player);
                    } else {
                        const a = document.createElement('a');
                        a.href = message.attachment_url;
                        a.target = '_blank';
                        a.rel = 'noopener';
                        a.textContent = `[FILE] ${message.attachment_name || 'Download file'}`;
                        wrap.appendChild(a);
                    }
                    article.appendChild(wrap);
                }

                const small = document.createElement('small');
                const fallbackTime = message.created_label || '';
                const timeOnly = fallbackTime.includes(',') ? fallbackTime.split(',').pop().trim() : fallbackTime;
                small.textContent = timeOnly;
                const ticks = document.createElement('span');
                const selfRead = isSelf && !!message.is_read;
                const incomingSeen = !isSelf;
                ticks.className = `chat-ticks${(selfRead || incomingSeen) ? ' is-read' : ''}`;
                ticks.textContent = isSelf ? (message.is_read ? '✓✓' : '✓') : '✓✓';
                small.appendChild(document.createTextNode(' '));
                small.appendChild(ticks);
                article.appendChild(small);

                const actions = document.createElement('details');
                actions.className = 'chat-msg-actions';
                const summary = document.createElement('summary');
                summary.textContent = '⋮';
                const menu = document.createElement('div');
                menu.className = 'chat-msg-actions-menu';
                const deleteForMeForm = document.createElement('form');
                deleteForMeForm.method = 'POST';
                deleteForMeForm.action = deleteMessageUrlTemplate.replace('__MSG__', String(message.id));
                deleteForMeForm.className = 'js-chat-delete-form';
                deleteForMeForm.setAttribute('data-message-id', String(message.id));
                deleteForMeForm.innerHTML = `<input type="hidden" name="_token" value="${token}"><input type="hidden" name="scope" value="me"><button type="submit">Delete for me</button>`;
                menu.appendChild(deleteForMeForm);
                if (isSelf) {
                    const deleteForAllForm = document.createElement('form');
                    deleteForAllForm.method = 'POST';
                    deleteForAllForm.action = deleteMessageUrlTemplate.replace('__MSG__', String(message.id));
                    deleteForAllForm.className = 'js-chat-delete-form';
                    deleteForAllForm.setAttribute('data-message-id', String(message.id));
                    deleteForAllForm.innerHTML = `<input type="hidden" name="_token" value="${token}"><input type="hidden" name="scope" value="everyone"><button type="submit">Delete for everyone</button>`;
                    menu.appendChild(deleteForAllForm);
                }
                actions.appendChild(summary);
                actions.appendChild(menu);
                article.appendChild(actions);

                const nearBottom = (chatWindow.scrollHeight - chatWindow.scrollTop - chatWindow.clientHeight) < 120;
                chatWindow.appendChild(article);
                chatWindow.dataset.lastMessageId = String(message.id);
                initAudioPlayers(article);
                if (nearBottom || isSelf) {
                    chatWindow.scrollTop = chatWindow.scrollHeight;
                }
            };

            const toClock = (seconds) => {
                const sec = Math.max(0, Math.floor(Number(seconds) || 0));
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return `${m}:${String(s).padStart(2, '0')}`;
            };

            const initAudioPlayers = (scope = document) => {
                scope.querySelectorAll('.chat-audio-msg').forEach((player) => {
                    if (player.dataset.inited === '1') return;
                    player.dataset.inited = '1';
                    const audio = player.querySelector('audio');
                    const playBtn = player.querySelector('.chat-audio-play');
                    const progress = player.querySelector('.chat-audio-progress');
                    const time = player.querySelector('.chat-audio-time');
                    if (!audio || !playBtn || !progress || !time) return;

                    const syncDuration = () => {
                        time.textContent = toClock(audio.duration || 0);
                    };

                    audio.addEventListener('loadedmetadata', syncDuration);
                    audio.addEventListener('timeupdate', () => {
                        const duration = audio.duration || 0;
                        const current = audio.currentTime || 0;
                        const percent = duration > 0 ? Math.min(100, (current / duration) * 100) : 0;
                        progress.value = String(percent);
                        time.textContent = toClock(duration - current);
                    });
                    audio.addEventListener('ended', () => {
                        playBtn.textContent = '▶';
                        progress.value = '0';
                        time.textContent = toClock(audio.duration || 0);
                    });
                    progress.addEventListener('input', () => {
                        const duration = audio.duration || 0;
                        const pct = Number(progress.value || 0);
                        if (duration > 0) audio.currentTime = (pct / 100) * duration;
                    });
                    playBtn.addEventListener('click', () => {
                        document.querySelectorAll('.chat-audio-msg audio').forEach((other) => {
                            if (other !== audio) {
                                other.pause();
                                const otherBtn = other.closest('.chat-audio-msg')?.querySelector('.chat-audio-play');
                                if (otherBtn) otherBtn.textContent = '▶';
                            }
                        });
                        if (audio.paused) {
                            audio.play().then(() => {
                                playBtn.textContent = '❚❚';
                            }).catch(() => {});
                        } else {
                            audio.pause();
                            playBtn.textContent = '▶';
                        }
                    });
                });
            };

            const pollUpdates = () => {
                if (!chatWindow) return;
                const afterId = Number(chatWindow.dataset.lastMessageId || 0);
                fetch(`${updatesUrl}?after_id=${encodeURIComponent(String(afterId))}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then((res) => res.ok ? res.json() : null)
                .then((data) => {
                    if (!data || !Array.isArray(data.messages)) return;
                    data.messages.forEach((message) => appendMessage(message));
                    const readUptoId = Number(data.read_upto_id || 0);
                    if (readUptoId > 0) {
                        chatWindow.querySelectorAll('.chat-bubble.is-self[data-message-id]').forEach((bubble) => {
                            const bubbleId = Number(bubble.getAttribute('data-message-id') || 0);
                            if (bubbleId > 0 && bubbleId <= readUptoId) {
                                const ticks = bubble.querySelector('.chat-ticks');
                                if (ticks) {
                                    ticks.textContent = '✓✓';
                                    ticks.classList.add('is-read');
                                }
                            }
                        });
                    }
                })
                .catch(() => {});
            };

            document.addEventListener('submit', (event) => {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-chat-delete-form')) return;
                event.preventDefault();
                const formData = new FormData(form);
                const messageId = Number(form.getAttribute('data-message-id') || 0);
                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                .then((res) => res.ok ? res.json() : Promise.reject())
                .then((data) => {
                    if (!messageId) return;
                    const bubble = chatWindow?.querySelector(`.chat-bubble[data-message-id="${messageId}"]`);
                    if (bubble) {
                        bubble.style.opacity = '.35';
                        bubble.style.transform = 'scale(.98)';
                        setTimeout(() => bubble.remove(), 120);
                    }
                })
                .catch(() => {});
            });

            const imageViewer = document.getElementById('chat-image-viewer');
            const imageViewerImg = document.getElementById('chat-image-viewer-img');
            const imageViewerClose = document.getElementById('chat-image-viewer-close');
            const openImageViewer = (src) => {
                if (!imageViewer || !imageViewerImg || !src) return;
                imageViewerImg.src = src;
                imageViewer.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };
            const closeImageViewer = () => {
                if (!imageViewer || !imageViewerImg) return;
                imageViewer.style.display = 'none';
                imageViewerImg.src = '';
                document.body.style.overflow = '';
            };
            imageViewerClose?.addEventListener('click', closeImageViewer);
            imageViewer?.addEventListener('click', (event) => {
                if (event.target === imageViewer) closeImageViewer();
            });
            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('.chat-image-open');
                if (!trigger) return;
                event.preventDefault();
                openImageViewer(trigger.getAttribute('data-image-src') || trigger.getAttribute('href'));
            });

            refreshPresence();
            setInterval(refreshPresence, 15000);
            pollUpdates();
            updateTimer = setInterval(pollUpdates, 8000);
            initAudioPlayers(document);

            const stopTracks = () => {
                if (stream) {
                    stream.getTracks().forEach((track) => track.stop());
                    stream = null;
                }
            };

            const setRecordingState = (isRecording) => {
                if (!recordBtn) return;
                recordBtn.classList.toggle('is-recording', isRecording);
                recordBtn.setAttribute('aria-label', isRecording ? 'Stop recording' : 'Record audio');
                recordBtn.title = isRecording ? 'Stop recording' : 'Record audio';
            };

            if (recordBtn && attachmentInput && navigator.mediaDevices?.getUserMedia) {
                recordBtn.addEventListener('click', async () => {
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                        return;
                    }
                    try {
                        stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        recordingChunks = [];
                        const preferred = [
                            'audio/webm;codecs=opus',
                            'audio/webm',
                            'audio/ogg;codecs=opus',
                            'audio/ogg',
                            'audio/mp4',
                        ];
                        recordingMimeType = preferred.find((type) => window.MediaRecorder?.isTypeSupported?.(type)) || '';
                        mediaRecorder = recordingMimeType
                            ? new MediaRecorder(stream, { mimeType: recordingMimeType })
                            : new MediaRecorder(stream);
                        mediaRecorder.ondataavailable = (event) => {
                            if (event.data && event.data.size > 0) recordingChunks.push(event.data);
                        };
                        mediaRecorder.onstop = () => {
                            const mime = mediaRecorder.mimeType || recordingMimeType || 'audio/webm';
                            const blob = new Blob(recordingChunks, { type: mime });
                            const ext = mime.includes('ogg') ? 'ogg' : (mime.includes('mp4') ? 'm4a' : (mime.includes('mpeg') ? 'mp3' : 'webm'));
                            const file = new File([blob], `voice-note-${Date.now()}.${ext}`, { type: blob.type || mime });
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            attachmentInput.files = dt.files;
                            stopTracks();
                            setRecordingState(false);
                        };
                        mediaRecorder.onerror = () => {
                            stopTracks();
                            setRecordingState(false);
                        };
                        mediaRecorder.start();
                        setRecordingState(true);
                    } catch (_) {
                        setRecordingState(false);
                    }
                });
            } else if (recordBtn) {
                recordBtn.style.display = 'none';
            }
        })();
    </script>
@endif

<style>
    .chat-shell { display: grid; grid-template-columns: 330px 1fr; gap: 12px; align-items: start; }
    .chat-sidebar { display: flex; flex-direction: column; gap: 8px; max-height: 80vh; overflow: auto; background: #111b21; border-color: #1f2c33; }
    .chat-thread { display: block; border: 1px solid transparent; border-radius: 10px; padding: 10px; background: #111b21; position: relative; min-height: auto; }
    .chat-thread.is-active { border-color: #2a3942; background: #202c33; }
    .chat-thread:hover { background: #1f2c33; }
    .chat-thread-top { display:flex; justify-content: space-between; gap: 8px; }
    .chat-thread-user { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .chat-thread-top strong { font-size: 14px; color: #e9edef; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-thread-top span { color: #8696a0; font-size: 11px; white-space: nowrap; }
    .chat-thread-item { margin: 7px 0 2px; color: #c4d0d6; font-size: 12px; }
    .chat-thread-preview { margin: 0; color: #8696a0; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-unread { position:absolute; top: 10px; right: 10px; min-width: 20px; height: 20px; border-radius: 999px; background: #25d366; color:#0b141a; font-weight:700; display:grid; place-items:center; font-size: 11px; }
    .chat-main { display: grid; grid-template-rows: auto auto auto 1fr auto; gap: 8px; min-height: 80vh; padding: 12px; background: #0b141a; border-color: #1f2c33; animation: chatFadeIn .35s ease; }
    .chat-context { display:flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #1f2c33; padding-bottom: 10px; }
    .chat-context h2 { margin: 0; }
    .chat-context-title { display: flex; align-items: center; gap: 10px; }
    .chat-context-sub { margin: 4px 0 0; color: #8fa2ad; }
    .chat-context-presence { margin: 4px 0 0; color: rgba(191,255,0,.9); font-size: 12px; }
    .chat-profile-card { margin-top: 9px; display: grid; gap: 4px; border: 1px solid #2a3942; border-radius: 10px; padding: 8px 10px; background: #111b21; }
    .chat-profile-card span { font-size: 12px; color: #d3dee3; }
    .chat-profile-details { margin-top: 8px; }
    .chat-profile-details summary { cursor: pointer; font-size: 12px; color: #9fc0ce; user-select: none; }
    .chat-profile-details[open] summary { margin-bottom: 6px; }
    .chat-confirmation-progress { margin-top: 10px; display: grid; gap: 6px; }
    .chat-confirm-pill { border: 1px solid rgba(255,255,255,.14); border-radius: 10px; padding: 7px 9px; background: rgba(255,255,255,.02); display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }
    .chat-confirm-pill strong { font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
    .chat-confirm-pill span { font-size: 12px; color: rgba(255,255,255,.66); }
    .chat-confirm-pill.is-done { border-color: rgba(191,255,0,.45); background: rgba(191,255,0,.09); }
    .chat-confirm-pill.is-done span { color: rgba(191,255,0,.94); }
    .chat-confirm-pill.is-pending { border-color: rgba(255,193,7,.32); background: rgba(255,193,7,.08); }
    .chat-context-right { display:flex; align-items:flex-start; gap: 10px; }
    .chat-status-pill { border:1px solid rgba(191,255,0,.45); color: var(--accent); border-radius:999px; padding:6px 10px; font-size:11px; letter-spacing:.1em; text-transform: uppercase; animation: pulseStatus 2.2s ease-in-out infinite; }
    .chat-safety { display:flex; gap: 8px; }
    .chat-actions { display:flex; gap: 8px; flex-wrap: wrap; }
    .chat-system-events { display:grid; gap: 6px; }
    .chat-system-line { border: 1px dashed rgba(191,255,0,.35); border-radius: 10px; padding: 8px 10px; display:flex; justify-content: space-between; gap: 10px; background: rgba(191,255,0,.06); }
    .chat-system-line strong { font-size: 13px; }
    .chat-system-line span { color: rgba(255,255,255,.55); font-size: 12px; }
    .chat-window { border: 1px solid #1f2c33; border-radius: 12px; padding: 12px; overflow:auto; min-height: 360px; max-height: 54vh; display: flex; flex-direction: column; gap: 7px; align-items: flex-start; background: #0b141a; background-image: radial-gradient(rgba(255,255,255,.03) 0.7px, transparent 0.7px); background-size: 18px 18px; }
    .chat-date-chip { justify-self: center; font-size: 11px; color: #c7d3d9; background: #1f2c33; border: 1px solid #2a3942; border-radius: 999px; padding: 4px 10px; margin: 6px 0 2px; }
    .chat-bubble { position: relative; max-width: 62%; border: 1px solid #2a3942; border-radius: 8px 8px 8px 3px; padding: 8px 38px 8px 10px; background: #202c33; animation: bubbleIn .24s ease; }
    .chat-bubble.is-self { margin-left: auto; border-color: #22413a; background: #005c4b; border-radius: 8px 8px 3px 8px; }
    .chat-bubble p { margin: 0; }
    .chat-bubble small { display:block; margin-top: 4px; color: #9bb0ba; font-size: 10px; text-align: right; letter-spacing: .02em; }
    .chat-msg-actions { position: absolute; top: 8px; right: 8px; }
    .chat-msg-actions summary { list-style: none; cursor: pointer; color: rgba(255,255,255,.92); font-size: 16px; font-weight: 700; line-height: 1; padding: 1px 3px; }
    .chat-msg-actions summary::-webkit-details-marker { display: none; }
    .chat-msg-actions-menu { position: absolute; right: 0; top: 18px; min-width: 150px; border: 1px solid #2a3942; border-radius: 8px; background: #111b21; padding: 6px; display: grid; gap: 5px; z-index: 8; }
    .chat-msg-actions-menu button { width: 100%; border: 1px solid #2a3942; border-radius: 7px; background: #1d2b33; color: #d8e3e8; padding: 6px 8px; text-align: left; font-size: 12px; cursor: pointer; }
    .chat-ticks { color: #bfd2db; font-weight: 800; letter-spacing: -0.06em; font-size: 11px; }
    .chat-ticks.is-read { color: #53d86a; }
    .chat-attachment { margin-top: 7px; }
    .chat-attachment a { color: rgba(191,255,0,.95); text-decoration: none; }
    .chat-attachment img { width: min(240px, 100%); max-height: 180px; object-fit: cover; border-radius: 12px; border: 1px solid rgba(255,255,255,.14); display: block; cursor: zoom-in; }
    .chat-audio-msg {
        width: min(300px, 100%);
        display: grid;
        grid-template-columns: 32px 1fr auto;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 999px;
        padding: 6px 10px 6px 6px;
        background: rgba(3, 21, 19, .35);
    }
    .chat-audio-msg audio { display: none; }
    .chat-audio-play {
        width: 30px;
        height: 30px;
        border: 0;
        border-radius: 999px;
        background: rgba(255,255,255,.14);
        color: #e8f7ff;
        cursor: pointer;
        font-size: 11px;
        line-height: 1;
    }
    .chat-audio-progress {
        width: 100%;
        accent-color: #53bdeb;
        cursor: pointer;
    }
    .chat-audio-time {
        font-size: 11px;
        color: #d5e6ee;
        min-width: 34px;
        text-align: right;
    }
    .chat-load-more { display:flex; justify-content:center; margin-bottom: 8px; }
    .chat-input { display:flex; gap: 8px; align-items: center; position: sticky; bottom: 0; background: #0b141a; padding-top: 6px; z-index: 12; }
    .chat-input input { flex: 1; background: #202c33; border: 1px solid #2a3942; border-radius: 999px; height: 42px; padding: 0 14px; color: #e9edef; }
    .chat-input .btn { border-radius: 999px; }
    .chat-attach-btn { width: 42px; min-width: 42px; height: 42px; padding: 0; display: inline-grid; place-items: center; }
    .chat-attach-btn svg { width: 18px; height: 18px; fill: currentColor; }
    .chat-voice-btn { min-width: 44px; width: 44px; height: 44px; padding: 0; display: inline-grid; place-items: center; }
    .chat-voice-btn svg { width: 18px; height: 18px; fill: currentColor; }
    .chat-voice-btn.is-recording { background: #ff4d4f; border-color: #ff4d4f; color: #fff; animation: pulseRecord 1s ease-in-out infinite; }
    .chat-input .btn.btn-primary { background: #25d366; border-color: #25d366; color: #0b141a; font-weight: 700; }
    .chat-attachment-input { display: none; }
    .chat-typing-indicator { margin: 0; color: rgba(191,255,0,.95); font-size: 12px; }
    .chat-avatar { width: 28px; height: 28px; border-radius: 999px; display: inline-grid; place-items: center; background: #2a3942; color: #e9edef; font-size: 11px; font-weight: 700; flex-shrink: 0; }
    .chat-empty { text-align: center; padding: 20px; }
    .chat-blocked-note { margin: 0; color: #ffb0b0; font-size: 13px; }
    .chat-image-viewer {
        position: fixed;
        inset: 0;
        z-index: 9999;
        align-items: center;
        justify-content: center;
        background: rgba(0,0,0,.88);
        padding: 16px;
    }
    .chat-image-viewer img {
        max-width: min(1100px, 96vw);
        max-height: 90vh;
        border-radius: 12px;
        object-fit: contain;
        box-shadow: 0 24px 50px rgba(0,0,0,.45);
    }
    .chat-image-viewer-close {
        position: fixed;
        top: 16px;
        right: 16px;
        border: 1px solid rgba(255,255,255,.3);
        border-radius: 999px;
        background: rgba(255,255,255,.12);
        color: #fff;
        padding: 8px 12px;
        cursor: pointer;
    }
    @keyframes chatFadeIn {
        from { opacity: .55; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes bubbleIn {
        from { opacity: .4; transform: translateY(4px) scale(.99); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes pulseStatus {
        0%, 100% { box-shadow: 0 0 0 0 rgba(191,255,0,.18); }
        50% { box-shadow: 0 0 0 7px rgba(191,255,0,0); }
    }
    @keyframes pulseRecord {
        0%, 100% { box-shadow: 0 0 0 0 rgba(255,77,79,.45); }
        50% { box-shadow: 0 0 0 8px rgba(255,77,79,0); }
    }
    @media (max-width: 980px) {
        .chat-shell { grid-template-columns: 1fr; }
        .chat-sidebar, .chat-main { min-height: auto; max-height: none; }
    }
    @media (max-width: 760px) {
        .chat-shell { gap: 10px; }
        .chat-sidebar {
            max-height: none;
            padding: 10px;
        }
        .chat-sidebar { display: none; }
        .chat-thread { padding: 9px; }
        .chat-main {
            min-height: auto;
            grid-template-rows: auto auto auto auto auto;
            padding: 10px 10px 92px;
            gap: 12px;
        }
        .chat-context {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .chat-context-title span:last-child { font-size: 2rem; font-weight: 700; line-height: 1; letter-spacing: -.01em; }
        .chat-context-sub { font-size: 1.2rem; }
        .chat-context-presence { font-size: 1rem; }
        .chat-profile-details summary { font-size: .98rem; }
        .chat-profile-card { width: 100%; padding: 12px; border-radius: 12px; }
        .chat-profile-card span { font-size: 1.05rem; line-height: 1.45; }
        .chat-context-right {
            width: 100%;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }
        .chat-safety {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }
        .chat-safety .btn { width: 100%; }
        .chat-actions {
            display: grid;
            grid-template-columns: 1fr;
        }
        .chat-actions .btn,
        .chat-actions form,
        .chat-actions form .btn {
            width: 100%;
        }
        .chat-system-line {
            flex-direction: column;
            align-items: flex-start;
        }
        .chat-window {
            min-height: 300px;
            max-height: 48vh;
            padding: 10px;
        }
        .chat-bubble {
            max-width: 88%;
            padding: 10px 38px 10px 12px;
            border-radius: 10px 10px 10px 4px;
        }
        .chat-bubble.is-self { border-radius: 10px 10px 4px 10px; }
        .chat-bubble p { font-size: 1.12rem; line-height: 1.35; }
        .chat-bubble small { font-size: .9rem; }
        .chat-date-chip { font-size: .88rem; padding: 5px 12px; margin: 8px auto 4px; }
        .chat-input {
            position: sticky;
            bottom: 70px;
            background: rgba(11,20,26,.96);
            padding: 8px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 16px;
            box-shadow: 0 10px 22px rgba(0,0,0,.45);
        }
        .chat-input input {
            height: 46px;
            font-size: 1.02rem;
        }
        .chat-input .btn {
            height: 42px;
            min-height: 42px;
            font-size: .9rem;
        }
        .chat-voice-btn {
            width: 42px;
            min-width: 42px;
        }
        .chat-attachment img { width: min(220px, 100%); max-height: 160px; }
    }
</style>
