@php
    $b1 = $board1 ?? null;
    $b2 = $board2 ?? null;
    $b3 = $board3 ?? null;
    $b4 = $board4 ?? null;
    $b5 = $board5 ?? null;
    $hasBoards = $b1 !== null || $b2 !== null || $b3 !== null || $b4 !== null || $b5 !== null;
    $itemCount = (int) (($b2 ?? [])['item_count'] ?? 0);
    $lowStock = (int) (($b2 ?? [])['low_stock'] ?? 0);
    $healthPct = $itemCount > 0 ? (int) round((($itemCount - $lowStock) / $itemCount) * 100) : 100;
@endphp

@if ($hasBoards)
<section class="overview-section overview-section--bi bi-dashboard" id="overview-bi" aria-labelledby="overviewBiHeading">
    <header class="bi-hero overview-section-head">
        <div class="bi-hero__content">
            <span class="bi-hero__eyebrow">📡 لوحات القيادة</span>
            <h2 id="overviewBiHeading" class="bi-hero__title">ذكاء الأعمال — لوحات مُصرّح بها</h2>
            <p class="bi-hero__desc">
                مؤشرات لحظية حسب صلاحياتك — المسارات، المخزون، التشغيل، الجهات، والمشتريات.
            </p>
        </div>
        <div class="bi-hero__kpis">
            @if ($b1 !== null)
                <div class="bi-hero-kpi">
                    <span class="bi-hero-kpi__label">إجمالي الحالات</span>
                    <strong class="bi-hero-kpi__value">{{ number_format($b1['total_cases'] ?? 0) }}</strong>
                </div>
            @endif
            @if ($b2 !== null)
                <div class="bi-hero-kpi bi-hero-kpi--cyan">
                    <span class="bi-hero-kpi__label">قيمة المخزون — متوسط التكلفة</span>
                    <strong class="bi-hero-kpi__value">{{ number_format((float) ($b2['total_value'] ?? 0), 0) }} <small>ج.م</small></strong>
                </div>
            @endif
            @if ($b3 !== null)
                <div class="bi-hero-kpi bi-hero-kpi--purple">
                    <span class="bi-hero-kpi__label">أوامر تشغيل مفتوحة</span>
                    <strong class="bi-hero-kpi__value">{{ number_format($b3['open_work_orders'] ?? 0) }}</strong>
                </div>
            @endif
            @if ($b2 !== null)
                <div class="bi-hero-kpi bi-hero-kpi--green">
                    <span class="bi-hero-kpi__label">صحة المخزون</span>
                    <strong class="bi-hero-kpi__value">{{ $healthPct }}%</strong>
                </div>
            @endif
        </div>
    </header>

    <nav class="bi-board-nav" aria-label="التنقل بين اللوحات">
        @if ($b1 !== null)
            <a href="#bi-board-1" class="bi-board-nav__link">👥 المرضى</a>
        @endif
        @if ($b2 !== null)
            <a href="#bi-board-2" class="bi-board-nav__link">📦 المخازن</a>
        @endif
        @if ($b3 !== null)
            <a href="#bi-board-3" class="bi-board-nav__link">🏭 التشغيل</a>
        @endif
        @if ($b4 !== null)
            <a href="#bi-board-4" class="bi-board-nav__link">🏢 الجهات</a>
        @endif
        @if ($b5 !== null)
            <a href="#bi-board-5" class="bi-board-nav__link">🛒 المشتريات</a>
        @endif
    </nav>

    <div id="biContent" data-server-rendered="1">
        @include('partials.dashboard-bi', [
            'board1' => $b1,
            'board2' => $b2,
            'board3' => $b3,
            'board4' => $b4,
            'board5' => $b5,
        ])
    </div>
</section>
@endif
