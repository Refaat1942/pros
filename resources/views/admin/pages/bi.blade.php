@php
    $b1 = $board1 ?? [];
    $b2 = $board2 ?? [];
    $b3 = $board3 ?? [];
    $b4 = $board4 ?? [];
    $b5 = $board5 ?? [];
    $hasBoards = isset($board1);
    $slaCount = (int) ($b1['sla_breached'] ?? count($b1['sla_breached_cases'] ?? []));
    $itemCount = (int) ($b2['item_count'] ?? 0);
    $lowStock = (int) ($b2['low_stock'] ?? 0);
    $healthPct = $itemCount > 0 ? (int) round((($itemCount - $lowStock) / $itemCount) * 100) : 100;
@endphp
<div class="section-view bi-dashboard" id="section-bi">
      <header class="bi-hero">
        <div class="bi-hero__content">
          <span class="bi-hero__eyebrow">📡 مركز القيادة</span>
          <h2 class="bi-hero__title">لوحات ذكاء الأعمال</h2>
          <p class="bi-hero__desc">
            مؤشرات لحظية من قاعدة البيانات: توزيع المسارات، SLA، قيمة المخزون (WAC)، خط الإنتاج، تكاليف الجهات، ومقارنة أسعار الشراء.
          </p>
        </div>
        @if ($hasBoards)
          <div class="bi-hero__kpis">
            <div class="bi-hero-kpi">
              <span class="bi-hero-kpi__label">إجمالي الحالات</span>
              <strong class="bi-hero-kpi__value">{{ number_format($b1['total_cases'] ?? 0) }}</strong>
            </div>
            <div class="bi-hero-kpi bi-hero-kpi--cyan">
              <span class="bi-hero-kpi__label">قيمة المخزون WAC</span>
              <strong class="bi-hero-kpi__value">{{ number_format((float) ($b2['total_value'] ?? 0), 0) }} <small>ج.م</small></strong>
            </div>
            <div class="bi-hero-kpi bi-hero-kpi--purple">
              <span class="bi-hero-kpi__label">أوامر تشغيل مفتوحة</span>
              <strong class="bi-hero-kpi__value">{{ number_format($b3['open_work_orders'] ?? 0) }}</strong>
            </div>
            <div class="bi-hero-kpi {{ $slaCount > 0 ? 'bi-hero-kpi--warn' : 'bi-hero-kpi--ok' }}">
              <span class="bi-hero-kpi__label">تجاوز SLA</span>
              <strong class="bi-hero-kpi__value">{{ $slaCount }}</strong>
            </div>
            <div class="bi-hero-kpi bi-hero-kpi--green">
              <span class="bi-hero-kpi__label">صحة المخزون</span>
              <strong class="bi-hero-kpi__value">{{ $healthPct }}%</strong>
            </div>
          </div>
        @endif
      </header>

      @if ($hasBoards)
        <nav class="bi-board-nav" aria-label="التنقل بين اللوحات">
          <a href="#bi-board-1" class="bi-board-nav__link">👥 المرضى</a>
          <a href="#bi-board-2" class="bi-board-nav__link">📦 المخازن</a>
          <a href="#bi-board-3" class="bi-board-nav__link">🏭 التشغيل</a>
          <a href="#bi-board-4" class="bi-board-nav__link">🏢 الجهات</a>
          <a href="#bi-board-5" class="bi-board-nav__link">🛒 المشتريات</a>
        </nav>
      @endif

      <div id="biContent" data-server-rendered="{{ $hasBoards ? '1' : '0' }}">
        @if ($hasBoards)
          @include('partials.dashboard-bi', [
            'board1' => $board1,
            'board2' => $board2,
            'board3' => $board3,
            'board4' => $board4,
            'board5' => $board5,
          ])
        @else
          @include('partials.dashboard-bi-empty')
        @endif
      </div>
    </div>
