@push('styles-late')
    <link rel="stylesheet" href="{{ asset('assets/css/notifications-inbox.css') }}">
@endpush

@php
    use App\Support\ArabicDate;

    /** @var \Illuminate\Pagination\LengthAwarePaginator $notifications */
    $items = $notifications ?? null;
    $filter = $notifications_filter ?? 'all';
    $stats = $notifications_stats ?? [];
    $inboxRoute = route("{$dashboardKey}.notifications");
@endphp

<div class="notif-inbox" id="notifInbox">
    {{-- إحصائيات سريعة --}}
    @if (!empty($stats))
        <div class="notif-stats">
            @foreach ($stats as $stat)
                <div class="notif-stat-card" style="--accent-bg: {{ $stat['bg'] ?? 'rgba(100,116,139,0.1)' }};">
                    <span class="notif-stat-icon">{{ $stat['icon'] }}</span>
                    <div>
                        <div class="notif-stat-label">{{ $stat['label'] }}</div>
                        <div class="notif-stat-value"
                            @if (!empty($stat['color'])) style="color:{{ $stat['color'] }}" @endif>
                            {{ $stat['value'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="notif-panel">
        <div class="notif-panel-head">
            <div>
                <h3>🔔 سجل الإشعارات</h3>
                <p>كل الإشعارات الواردة (بلا استعلام دوري).</p>
            </div>
            <div class="notif-panel-actions">
                <div class="notif-tabs">
                    <a href="{{ $inboxRoute }}" class="notif-tab {{ $filter === 'all' ? 'active' : '' }}">الكل</a>
                    <a href="{{ $inboxRoute }}?filter=unread"
                        class="notif-tab {{ $filter === 'unread' ? 'active' : '' }}">غير مقروء</a>
                </div>
                @if ($items && $items->total() > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" class="notif-mark-all-form">
                        @csrf
                        <button type="submit" class="notif-btn notif-btn-outline">✓ تعليم الكل كمقروء</button>
                    </form>
                @endif
            </div>
        </div>

        @if ($items && $items->count() > 0)
            <ul class="notif-feed">
                @foreach ($items as $notification)
                    <li class="notif-card {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                        <div class="notif-card-accent"></div>
                        <div class="notif-card-body">
                            <div class="notif-card-top">
                                <span class="notif-card-icon">{{ $notification->read_at ? '📭' : '📬' }}</span>
                                <div class="notif-card-meta">
                                    <h4>{{ $notification->title }}</h4>
                                    <time datetime="{{ $notification->created_at?->toIso8601String() }}">
                                        {{ ArabicDate::relative($notification->created_at) }}
                                    </time>
                                </div>
                                @unless ($notification->read_at)
                                    <span class="notif-pill-new">جديد</span>
                                @endunless
                            </div>
                            <p class="notif-card-text">{{ $notification->body }}</p>
                            @if ($notification->caseRecord)
                                <div class="notif-card-case">
                                    <span>📁 حالة:</span>
                                    <strong>{{ $notification->caseRecord->case_no }}</strong>
                                    @if ($notification->caseRecord->order_ref)
                                        <span class="notif-muted">· {{ $notification->caseRecord->order_ref }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        @unless ($notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification) }}"
                                class="notif-card-action">
                                @csrf
                                <button type="submit" class="notif-btn notif-btn-read">✓ مقروء</button>
                            </form>
                        @endunless
                    </li>
                @endforeach
            </ul>

            <div class="notif-pagination-wrap">
                {{ $items->links('partials.pagination') }}
            </div>
        @else
            <div class="notif-empty-state">
                <div class="notif-empty-icon">🔕</div>
                <h4>لا توجد إشعارات{{ $filter === 'unread' ? ' غير مقروءة' : '' }}</h4>
            </div>
        @endif
    </div>
</div>
