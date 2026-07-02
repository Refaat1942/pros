<div class="section-view overview-page" id="section-overview" data-server-rendered="1">

    <form method="GET" action="{{ route('admin.overview') }}" class="reports-date-filter overview-date-filter" id="overviewDateFilter">
        <label>
            <span>من</span>
            <input type="date" name="from" value="{{ $date_from ?? now()->startOfMonth()->toDateString() }}" required>
        </label>
        <label>
            <span>إلى</span>
            <input type="date" name="to" value="{{ $date_to ?? now()->toDateString() }}" required>
        </label>
        <button type="submit" class="btn-action primary">تطبيق الفترة</button>
        <a href="{{ route('admin.overview') }}" class="btn-action">مسح الفلتر</a>
        <a href="{{ route('admin.overview.export', ['from' => $date_from ?? now()->startOfMonth()->toDateString(), 'to' => $date_to ?? now()->toDateString()]) }}"
           class="btn-export excel"
           download>📊 تصدير Excel</a>
    </form>

    <section class="overview-section overview-section--cycle" aria-labelledby="overviewCycleHeading">
        <header class="overview-section-head">
            <h2 id="overviewCycleHeading">🔄 دورة العمل — الطوابير الحية</h2>
            <p>عدد الطلبات في كل لوحة — {{ $period_label ?? '' }} — {{ $cycle_total_active ?? 0 }} حالة نشطة في الفترة</p>
        </header>
        <div class="overview-cycle-grid" id="overviewCycleGrid">
            @foreach ($cycle_cards ?? [] as $card)
                <div class="overview-cycle-card" style="--cycle-color: {{ $card['color'] }}; --cycle-bg: {{ $card['bg'] }};" data-cycle-key="{{ $card['key'] }}">
                    <span class="overview-cycle-card__icon" aria-hidden="true">{{ $card['icon'] }}</span>
                    <div class="overview-cycle-card__body">
                        <span class="overview-cycle-card__count" data-server-rendered="1">{{ $card['count'] }}</span>
                        <span class="overview-cycle-card__label">{{ $card['label'] }}</span>
                        <span class="overview-cycle-card__hint">{{ $card['hint'] }}</span>
                    </div>
                </div>
                @unless ($loop->last)
                    <span class="overview-cycle-arrow" aria-hidden="true">←</span>
                @endunless
            @endforeach
        </div>
    </section>

    <section class="overview-section" aria-label="حالة الحالات">
        <header class="overview-section-head overview-section-head--compact">
            <h2>📂 متابعة الحالات</h2>
            <p>انتقال سريع إلى قائمة الحالات — {{ $period_label ?? '' }}</p>
        </header>
        <div class="overview-cases-strip" id="overviewCasesStrip">
            <button type="button" class="overview-case-link overview-case-link--wait" data-goto-cases="waiting_return">
                <div class="overview-case-link__text">
                    <strong>بانتظار موافقة جهات التعاقد</strong>
                    <span class="overview-case-link__hint">موافقات وتوقيعات</span>
                </div>
                <span id="overviewWaitingCount" class="overview-case-link__count" data-server-rendered="1">{{ $case_strip['waiting_return'] ?? 0 }}</span>
            </button>
            <button type="button" class="overview-case-link overview-case-link--cashier" data-goto-cases="awaiting_cashier">
                <div class="overview-case-link__text">
                    <strong>بانتظار الدفع النقدي — الخزنة</strong>
                    <span class="overview-case-link__hint">مرضى الكاش — تحصيل المبلغ</span>
                </div>
                <span id="overviewCashierCount" class="overview-case-link__count" data-server-rendered="1">{{ $case_strip['awaiting_cashier'] ?? 0 }}</span>
            </button>
            <button type="button" class="overview-case-link overview-case-link--progress" data-goto-cases="in_progress">
                <div class="overview-case-link__text">
                    <strong>تحت التنفيذ</strong>
                    <span class="overview-case-link__hint">تصنيع وتشغيل</span>
                </div>
                <span id="overviewProgressCount" class="overview-case-link__count" data-server-rendered="1">{{ $case_strip['in_progress'] ?? 0 }}</span>
            </button>
            <button type="button" class="overview-case-link overview-case-link--done" data-goto-cases="delivered">
                <div class="overview-case-link__text">
                    <strong>تم التسليم</strong>
                    <span class="overview-case-link__hint">حالات مُغلقة</span>
                </div>
                <span id="overviewDeliveredCount" class="overview-case-link__count" data-server-rendered="1">{{ $case_strip['delivered'] ?? 0 }}</span>
            </button>
        </div>
    </section>

    @include('admin.partials.overview-bi')
</div>
