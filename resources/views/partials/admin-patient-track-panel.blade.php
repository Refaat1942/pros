@php
    $tracks = collect($patient_tracks ?? []);
    $trackSearch = $track_search ?? '';
    $trackStage = $track_stage ?? '';
    $trackPatientType = $track_patient_type ?? '';
    $trackVisitType = $track_visit_type ?? '';
    $trackStageOptions = $track_stage_options ?? [];
    $trackVisitOptions = $track_visit_options ?? [];
@endphp
<script>
window.__patientTracksById = @json($tracks->keyBy('id')->all());
</script>
<div class="panel patient-track-panel" id="patientTrackPanel">
    <div class="panel-header">
        <h3>📍 مسار المرضى — تتبع المراحل</h3>
        <div class="patient-track-actions">
            <span class="patient-track-count" id="patientTrackBadge">{{ $tracks->count() }} مريض</span>
            <button type="button" class="patient-track-refresh" id="patientTrackRefresh" title="تحديث" aria-label="تحديث">↺</button>
        </div>
    </div>
    <div class="data-toolbar patient-track-toolbar">
        <input type="text"
               id="patientTrackSearch"
               value="{{ $trackSearch }}"
               placeholder="🔍 بحث بالاسم أو الهاتف أو الرقم القومي..."
               autocomplete="off">
        <select id="patientTrackStageFilter" class="patient-track-filter-select" aria-label="فلتر المرحلة">
            <option value="">كل المراحل</option>
            @foreach ($trackStageOptions as $option)
                <option value="{{ $option['value'] }}" @selected($trackStage === $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
        <select id="patientTrackTypeFilter" class="patient-track-filter-select" aria-label="فلتر النوع">
            <option value="">مدني وعسكري</option>
            <option value="civilian" @selected($trackPatientType === 'civilian')>🌐 مدني</option>
            <option value="military" @selected($trackPatientType === 'military')>🪖 عسكري</option>
        </select>
        <select id="patientTrackVisitFilter" class="patient-track-filter-select" aria-label="فلتر نوع الزيارة">
            <option value="">كل الزيارات</option>
            @foreach ($trackVisitOptions as $option)
                <option value="{{ $option['value'] }}" @selected((string) $trackVisitType === (string) $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
        <span class="toolbar-count" id="patientTrackFilterCount">{{ $tracks->count() }} مريض</span>
    </div>
    <div class="panel-body patient-track-table-wrap">
        <table data-paginate="10" class="patient-track-table">
            <thead>
                <tr>
                    <th>المريض</th>
                    <th>النوع</th>
                    <th>التواصل</th>
                    <th>المرحلة الحالية</th>
                    <th class="col-actions">إجراء</th>
                </tr>
            </thead>
            <tbody id="patientTrackTableBody">
                @forelse ($tracks as $track)
                    <tr class="patient-track-row"
                        data-search="{{ $track['search_hay'] ?? '' }}"
                        data-stage-key="{{ $track['stage_key'] ?? '' }}"
                        data-pathway="{{ $track['pathway'] ?? '' }}"
                        data-visit-type-id="{{ $track['visit_type_id'] ?? '' }}">
                        <td>
                            <strong>{{ $track['name'] }}</strong>
                            @if (! empty($track['case_no']))
                                <div class="patient-track-cell-sub">{{ $track['case_no'] }}</div>
                            @endif
                            @if (! empty($track['company_name']))
                                <div class="patient-track-cell-sub">{{ $track['company_name'] }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="patient-type-badge {{ $track['pathway'] === 'military' ? 'military' : 'civilian' }}">
                                {{ $track['pathway'] === 'military' ? '🪖 عسكري' : '🌐 مدني' }}
                            </span>
                        </td>
                        <td class="patient-track-contact">
                            @if (! empty($track['phone']))
                                <span dir="ltr">{{ $track['phone'] }}</span>
                            @endif
                            @if (! empty($track['national_id']))
                                <span dir="ltr">{{ $track['national_id'] }}</span>
                            @endif
                            @if (empty($track['phone']) && empty($track['national_id']))
                                —
                            @endif
                        </td>
                        <td>
                            <span class="patient-track-stage-inline">{{ $track['stage_label'] }}</span>
                            <span class="patient-track-percent-inline">{{ $track['progress_percent'] }}%</span>
                        </td>
                        <td class="col-actions">
                            <div class="patient-track-action-btns">
                                <button type="button"
                                        class="btn-action primary btn-view-patient-track"
                                        data-track-id="{{ $track['id'] }}">
                                    📍 عرض المسار
                                </button>
                                <button type="button"
                                        class="btn-action btn-view-patient-details"
                                        data-track-id="{{ $track['id'] }}">
                                    👤 تفاصيل المريض
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="patient-track-empty">
                            ✅ لا يوجد مرضى مطابقون للفلتر الحالي — يشمل المسار الحالات النشطة والمُسلَّمة.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="catalog-modal-overlay" id="patientTrackModal" role="dialog" aria-modal="true" aria-labelledby="patientTrackModalTitle">
    <div class="catalog-modal patient-track-modal" onclick="event.stopPropagation()" style="max-width:920px;">
        <div class="catalog-modal-header">
            <div>
                <h3 id="patientTrackModalTitle">📍 مسار المريض</h3>
                <div class="modal-code" id="patientTrackModalMeta">—</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closePatientTrackModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body patient-track-modal-body">
            <div class="patient-track-modal-head">
                <div class="patient-track-identity">
                    <strong id="patientTrackModalName">—</strong>
                    <span class="patient-type-badge" id="patientTrackModalBadge">—</span>
                </div>
                <span class="patient-track-percent" id="patientTrackModalPercent">0%</span>
            </div>
            <p class="patient-track-stage" id="patientTrackModalStage">—</p>
            <div class="patient-track-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                <div class="patient-track-bar-fill" id="patientTrackModalBar" style="width:0%"></div>
            </div>
            <div class="patient-track-steps" id="patientTrackModalSteps" aria-hidden="true"></div>
            <p class="patient-track-pathway-note" id="patientTrackModalPathNote"></p>
            <div class="patient-track-journey" id="patientTrackModalJourney" aria-live="polite"></div>
        </div>
        <div class="catalog-modal-footer">
            <button type="button" class="btn-action" id="btnClosePatientTrackModal">إغلاق</button>
        </div>
    </div>
</div>

<div class="catalog-modal-overlay" id="patientDetailsModal" role="dialog" aria-modal="true" aria-labelledby="patientDetailsModalTitle">
    <div class="catalog-modal patient-details-modal" onclick="event.stopPropagation()">
        <div class="catalog-modal-header patient-details-modal-header">
            <div>
                <h3 id="patientDetailsModalTitle">👤 تفاصيل المريض</h3>
                <div class="modal-code" id="patientDetailsModalMeta">—</div>
            </div>
            <button type="button" class="catalog-modal-close" id="closePatientDetailsModal" aria-label="إغلاق">&times;</button>
        </div>
        <div class="catalog-modal-body patient-details-modal-body" id="patientDetailsModalBody"></div>
        <div class="catalog-modal-footer patient-details-modal-footer">
            <button type="button" class="btn-action primary" id="btnClosePatientDetailsModal">إغلاق</button>
        </div>
    </div>
</div>

@include('partials.contract-letter-modal')

<div class="modal-overlay journey-quote-preview-modal" id="journeyQuotePreviewModal"
     style="display:none;position:fixed;inset:0;z-index:1100;background:rgba(15,23,42,.65);
            backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px;">
    <div class="journey-quote-preview-dialog">
        <div class="journey-quote-preview-header">
            <h3 id="journeyQuotePreviewTitle">🧾 عرض السعر</h3>
            <button type="button" id="btnCloseJourneyQuotePreview" class="journey-quote-preview-close" aria-label="إغلاق">&times;</button>
        </div>
        <div id="journeyQuotePreviewBody" class="journey-quote-preview-body"></div>
    </div>
</div>
