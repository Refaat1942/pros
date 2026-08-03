@php
    /** @var \Illuminate\Support\Collection $roles */
    /** @var array $dashboards */
    /** @var \Illuminate\Support\Collection $matrix */
    /** @var \Illuminate\Support\Collection $permission_ids */
    $roles = $roles ?? collect();
    $dashboards = $dashboards ?? [];
    $matrix = $matrix ?? collect();
    $permissionIds = $permission_ids ?? collect();
    $catalog = \App\Models\Permission::catalog();
@endphp

<div class="perm-page">
    <div class="perm-page-header">
        <div>
            <h3 class="perm-page-title">🛡️ إدارة الصلاحيات</h3>
            <p class="perm-page-subtitle">
                <strong>سوبر أدمن</strong> يتحكم في كل شيء ويضبط من هنا ما يراه كل دور —
                بما في ذلك حسابات «مسؤول النظام (محدود)» التي تُنشأ من صفحة الموظفين.
                السوبر أدمن نفسه لا يظهر في المصفوفة لأنه يملك كل الصلاحيات تلقائياً.
                <br>
                <span class="perm-role-banner-hint">تفعيل «عرض الصفحة» يكفي لتشغيل الشاشة — لا حاجة لتفعيل إجراء منفصل لنفس الشاشة.</span>
            </p>
        </div>
        <div class="perm-header-actions">
            <div class="perm-role-picker">
                <label for="permRoleSelect">الدور المُعدَّل</label>
                <select id="permRoleSelect" class="perm-role-select">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" data-slug="{{ $role->slug }}">
                            {{ $role->label_ar }} ({{ $role->slug }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="button" id="permCheckAllBtn" class="perm-check-all-btn">
                ✅ تحديد الكل
            </button>
            <button type="button" class="btn-export excel" data-export-permissions="1" data-export-filename="مصفوفة_الصلاحيات">📊 Excel</button>
        </div>
    </div>

    <div class="perm-role-banner" id="permRoleBanner">
        تعدّل صلاحيات: <strong id="permRoleBannerName">{{ $roles->first()?->label_ar }}</strong>
        <span class="perm-role-banner-hint">— التغييرات تُطبَّق على هذا الدور فقط عند الحفظ</span>
    </div>

    @if (session('success') || session('status'))
        <div class="perm-flash-success flash-message flash-success" role="alert">
            ✅ {{ session('success') ?? session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="perm-flash-error flash-message flash-error" role="alert">⚠️ {{ session('error') }}</div>
    @endif

    <div class="perm-session-info">
        مسجّل كـ <strong>{{ auth()->user()?->username }}</strong>
        — دور: <code>{{ auth()->user()?->role?->slug ?? '—' }}</code>
        @if (auth()->user()?->isSuperAdmin())
            <span class="perm-session-ok">✓ سوبر أدمن — الحفظ مفعّل</span>
        @else
            <span class="perm-session-warn">✗ ليس super_admin — الحفظ معطّل</span>
        @endif
    </div>

    @if (auth()->user()?->isSuperAdmin())
        <div class="perm-test-hint">
            <strong>لاختبار الصلاحيات:</strong> بعد الحفظ سجّل خروجاً ثم ادخل بحساب الدور نفسه
            (مثلاً <code>reception</code> / <code>123456</code>).
            السوبر أدمن يرى <em>كل</em> الصفحات دائماً — شريط «تنقّل بين الأقسام» لا يُ simulates دور الاستقبال.
        </div>
    @else
        <div class="perm-flash-error">
            ⚠️ أنت تتصفّح كـ «{{ auth()->user()?->role?->label_ar }}» — <strong>الحفظ متاح لـ superadmin فقط</strong>.
            سجّل دخول بحساب <code>superadmin</code> (دور <code>super_admin</code>) لتعديل الصلاحيات.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.permissions.update') }}" id="permMatrixForm"
          data-page-action-aliases='@json(config('permissions.page_action_aliases', []))'>
        @csrf

        {{-- مصفوفة كاملة مخفية — تُحدَّث عبر JS عند التبديل بين الأدوار --}}
        <div id="permHiddenMatrix" class="perm-hidden-matrix" aria-hidden="true">
            @foreach ($roles as $role)
                @php $slugs = (array) ($matrix[$role->id] ?? []); @endphp
                <div class="perm-role-store" data-role-id="{{ $role->id }}" data-slugs="{{ json_encode($slugs) }}">
                    @foreach ($catalog as $slug => $meta)
                        @php $permId = $permissionIds[$slug] ?? null; @endphp
                        @if ($permId)
                            <input type="checkbox"
                                   class="perm-hidden-cb"
                                   data-role="{{ $role->id }}"
                                   data-slug="{{ $slug }}"
                                   name="matrix[{{ $role->id }}][]"
                                   value="{{ $permId }}"
                                   {{ in_array($slug, $slugs, true) ? 'checked' : '' }}>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="perm-cards-grid">
            @foreach ($dashboards as $dash)
                @if ($dash['views']->isEmpty() && $dash['actions']->isEmpty())
                    @continue
                @endif
                <article class="perm-card" data-dashboard="{{ $dash['key'] }}">
                    <header class="perm-card-header">
                        <span class="perm-card-icon">{{ $dash['icon'] }}</span>
                        <div>
                            <h4 class="perm-card-title">{{ $dash['label'] }}</h4>
                            <span class="perm-card-meta">
                                {{ $dash['views']->count() }} صفحة
                                @if ($dash['actions']->isNotEmpty())
                                    · {{ $dash['actions']->count() }} إجراء
                                @endif
                            </span>
                        </div>
                        <div class="perm-card-actions">
                            <button type="button"
                                    class="perm-card-toggle-btn"
                                    data-dashboard="{{ $dash['key'] }}"
                                    data-lock="1"
                                    title="إيقاف كل صلاحيات هذه اللوحة للدور المختار">
                                🔒 قفل
                            </button>
                            <button type="button"
                                    class="perm-card-toggle-btn"
                                    data-dashboard="{{ $dash['key'] }}"
                                    data-lock="0"
                                    title="تفعيل كل صلاحيات هذه اللوحة للدور المختار">
                                🔓 فتح
                            </button>
                        </div>
                    </header>

                    @if (!empty($dash['groups']))
                        @foreach ($dash['groups'] as $group)
                            <section class="perm-card-section">
                                <h5 class="perm-section-label">
                                    <span class="perm-badge perm-badge-view">{{ $group['icon'] ?? '📁' }}</span>
                                    {{ $group['label'] }}
                                </h5>
                                <ul class="perm-toggle-list">
                                    @foreach ($group['views'] as $perm)
                                        <li class="perm-toggle-item">
                                            <label class="perm-toggle">
                                                <input type="checkbox"
                                                       class="perm-visible-cb"
                                                       data-perm-id="{{ $perm->id }}"
                                                       data-slug="{{ $perm->slug }}">
                                                <span class="perm-toggle-track"><span class="perm-toggle-thumb"></span></span>
                                                <span class="perm-toggle-text">
                                                    <strong>{{ $perm->label_ar }}</strong>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    @elseif ($dash['views']->isNotEmpty())
                        <section class="perm-card-section">
                            <h5 class="perm-section-label">
                                <span class="perm-badge perm-badge-view">عرض</span>
                                الصفحات
                            </h5>
                            <ul class="perm-toggle-list">
                                @foreach ($dash['views'] as $perm)
                                    <li class="perm-toggle-item">
                                        <label class="perm-toggle">
                                            <input type="checkbox"
                                                   class="perm-visible-cb"
                                                   data-perm-id="{{ $perm->id }}"
                                                   data-slug="{{ $perm->slug }}">
                                            <span class="perm-toggle-track"><span class="perm-toggle-thumb"></span></span>
                                            <span class="perm-toggle-text">
                                                <strong>{{ $perm->label_ar }}</strong>
                                            </span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @if ($dash['actions']->isNotEmpty())
                        <section class="perm-card-section">
                            <h5 class="perm-section-label">
                                <span class="perm-badge perm-badge-action">إجراء</span>
                                الصلاحيات
                            </h5>
                            <ul class="perm-toggle-list">
                                @foreach ($dash['actions'] as $perm)
                                    <li class="perm-toggle-item">
                                        <label class="perm-toggle">
                                            <input type="checkbox"
                                                   class="perm-visible-cb"
                                                   data-perm-id="{{ $perm->id }}"
                                                   data-slug="{{ $perm->slug }}">
                                            <span class="perm-toggle-track"><span class="perm-toggle-thumb"></span></span>
                                            <span class="perm-toggle-text">
                                                <strong>{{ $perm->label_ar }}</strong>
                                            </span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                </article>
            @endforeach
        </div>

        <footer class="perm-page-footer">
            @if (auth()->user()?->isSuperAdmin())
                <button type="submit" class="btn-action success perm-save-btn">💾 حفظ الصلاحيات</button>
            @else
                <button type="button" class="btn-action perm-save-btn" disabled title="متاح لـ superadmin فقط">
                    💾 حفظ الصلاحيات (سوبر أدمن فقط)
                </button>
            @endif
        </footer>
    </form>
</div>

@if (session('success') || session('status'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var msg = @json(session('success') ?? session('status'));
            if (window.DashboardToast && msg) {
                window.DashboardToast.show(msg, 'success');
            }
            var flash = document.querySelector('.perm-flash-success');
            if (flash) {
                flash.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    </script>
@endif
