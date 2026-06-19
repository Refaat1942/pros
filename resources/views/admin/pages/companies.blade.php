@php
    $companyList = $companies ?? collect();
    $withDebt = $companyList->filter(fn ($c) => $c->debt)->count();
@endphp
<div class="section-view" id="section-companies">
    <div id="analytics-companies">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🏢', 'label' => 'شركات', 'value' => (string) $companyList->count(), 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'لها مديونيات', 'value' => (string) $withDebt, 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '➕', 'label' => 'بدون مديونيات', 'value' => (string) max(0, $companyList->count() - $withDebt), 'bg' => 'rgba(100,116,139,0.1)'],
        ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
    ]])</div>
    <div class="panel">
        <div class="panel-header">
            <h3>🏢 شركات التعاقد</h3>
            <span class="badge" id="companiesBadge">{{ $companyList->count() }} شركة</span>
        </div>
        <form method="POST" action="{{ route('admin.companies.store') }}" class="company-add-bar" data-validate-form>
            @csrf
            <input type="hidden" name="form" value="company">
            <input type="hidden" name="is_military" value="0">
            <input type="text" name="name" placeholder="اسم الشركة / جهة التعاقد..." autocomplete="off"
                   data-v-rules="required,min:2,max:255" maxlength="255"
                   value="{{ old('name') }}">
            <button type="submit" class="btn-add-company">➕ إضافة شركة</button>
            <p class="company-hint">أضف اسم جهة التعاقد — تُستخدم في الاستقبال والمديونيات والتقارير</p>
        </form>
        <div class="data-toolbar">
            <input type="text" id="companySearch" placeholder="🔍 بحث باسم الشركة...">
            <span class="toolbar-count" id="companiesCount">{{ $companyList->count() }} شركة</span>
        </div>
        <div class="panel-body">
            <table data-paginate="10">
                <thead>
                    <tr>
                        <th style="width:48px">#</th>
                        <th>اسم الشركة</th>
                        <th>الكود</th>
                        <th style="width:100px">إجراء</th>
                    </tr>
                </thead>
                <tbody id="companiesTable" data-server-rendered="1">
                    @forelse ($companyList as $company)
                        <tr data-name="{{ $company->name }}">
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $company->name }}</strong></td>
                            <td>{{ $company->company_code }}</td>
                            <td>—</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align:center;color:var(--text-muted);padding:24px;">
                                لا توجد شركات — أضف جهة تعاقد من الحقل أعلاه.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
