<div class="section-view" id="section-companies">
      <div id="analytics-companies">@include('partials.dashboard-analytics-empty', ['stats' => [
        ['icon' => '🏢', 'label' => 'شركات', 'value' => '0', 'bg' => 'rgba(124,58,237,0.1)'],
        ['icon' => '💰', 'label' => 'لها مديونيات', 'value' => '0', 'color' => '#0e7490', 'bg' => 'rgba(14,116,144,0.1)'],
        ['icon' => '➕', 'label' => 'بدون مديونيات', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
        ['icon' => '📊', 'label' => '—', 'value' => '0', 'bg' => 'rgba(100,116,139,0.1)'],
      ]])</div>
      <div class="panel">
        <div class="panel-header">
          <h3>🏢 شركات التعاقد</h3>
          <span class="badge" id="companiesBadge">0 شركة</span>
        </div>
        <div class="company-add-bar">
          <input type="text" id="companyNameInput" placeholder="اسم الشركة / جهة التعاقد..." autocomplete="off">
          <button type="button" class="btn-add-company" id="btnAddCompany">➕ إضافة شركة</button>
          <p class="company-hint">أضف اسم جهة التعاقد فقط — تُستخدم في قسم المديونيات والتقارير</p>
        </div>
        <div class="data-toolbar">
          <input type="text" id="companySearch" placeholder="🔍 بحث باسم الشركة...">
          <span class="toolbar-count" id="companiesCount">0 شركة</span>
          <div class="export-btns">
            <button class="btn-export excel" onclick="exportCompanies('excel')">📊 Excel</button>
            <button class="btn-export pdf" onclick="exportCompanies('pdf')">📄 PDF</button>
          </div>
        </div>
        <div class="panel-body">
          <table>
            <thead>
              <tr>
                <th style="width:48px">#</th>
                <th>اسم الشركة</th>
                <th style="width:100px">إجراء</th>
              </tr>
            </thead>
            <tbody id="companiesTable"></tbody>
          </table>
        </div>
      </div>
    </div>
