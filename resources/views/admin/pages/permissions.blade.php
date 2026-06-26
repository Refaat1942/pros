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
                تحكّم في صلاحيات العرض والإجراءات لكل دور. مسؤول النظام يصل تلقائياً للوحة الإدارة،
                ويمكنه زيارة اللوحات الأخرى حسب الصلاحيات الممنوحة هنا.
            </p>
        </div>
        <div class="perm-header-actions">
            <div class="perm-role-picker">
                <label for="permRoleSelect">الدور المُعدَّل</label>
                <select id="permRoleSelect" class="perm-role-select">
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" data-slug="{{ $role->slug }}">
                            {{ $role->label_ar }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="button" id="permCheckAllBtn" class="perm-check-all-btn">
                ✅ تحديد الكل
            </button>
            <button type="button" class="btn-export excel" data-export-permissions="1" data-export-filename="permissions-matrix">📊 Excel</button>
        </div>
    </div>

    <div class="perm-role-banner" id="permRoleBanner">
        تعدّل صلاحيات: <strong id="permRoleBannerName">{{ $roles->first()?->label_ar }}</strong>
        <span class="perm-role-banner-hint">— التغييرات تُطبَّق على هذا الدور فقط عند الحفظ</span>
    </div>

    @if (session('status'))
        <div class="perm-flash-success">✅ {{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.permissions.update') }}" id="permMatrixForm">
        @csrf

        {{-- مصفوفة كاملة مخفية — تُحدَّث عبر JS عند التبديل بين الأدوار --}}
        <div id="permHiddenMatrix" class="perm-hidden-matrix" aria-hidden="true">
            @foreach ($roles as $role)
                @php $slugs = (array) ($matrix[$role->id] ?? []); @endphp
                <div class="perm-role-store" data-role-id="{{ $role->id }}" data-slugs="{{ json_encode($slugs) }}">
                    @foreach ($catalog as $slug => $meta)
                        @continue(($meta['dashboard'] ?? '') === 'admin')
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
                    </header>

                    @if ($dash['views']->isNotEmpty())
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
            <button type="submit" class="btn-action success perm-save-btn">💾 حفظ الصلاحيات</button>
        </footer>
    </form>
</div>

@push('scripts')
    <script src="{{ asset('assets/js/pages/admin-permissions.js') }}?v={{ filemtime(public_path('assets/js/pages/admin-permissions.js')) }}"></script>
@endpush
