<div class="section-view" id="section-documents-hub">
    <div class="panel">
        <div class="panel-header">
            <h3>📄 مركز الوثائق والطباعة</h3>
            <p style="margin:8px 0 0;font-size:13px;color:var(--text-muted);">
                اختر وثيقة للطباعة أو لتخصيص شكلها ومحتواها الثابت (عنوان، ترويسة، توقيعات).
                الشعار وأسطر الجهة من <a href="{{ url('/admin/branding-settings') }}">الهوية البصرية</a>.
            </p>
        </div>
        <div class="panel-body" style="display:grid;gap:20px;">
            @foreach ($documents as $group)
                <section>
                    <h4 style="margin:0 0 12px;font-size:15px;">{{ $group['group'] }}</h4>
                    <div style="display:grid;gap:10px;">
                        @foreach ($group['items'] as $doc)
                            <div style="border:1px solid var(--border);border-radius:10px;padding:14px 16px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;">
                                <div>
                                    <strong>{{ $doc['title'] }}</strong>
                                    <p style="margin:4px 0 0;font-size:13px;color:var(--text-muted);">{{ $doc['description'] }}</p>
                                </div>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                    @if (!empty($doc['preview_url']))
                                        <a href="{{ $doc['preview_url'] }}" target="_blank" rel="noopener" class="btn-action">👁️ معاينة</a>
                                    @endif
                                    @if (!empty($doc['print_url']))
                                        <a href="{{ $doc['print_url'] }}" target="_blank" rel="noopener" class="btn-action primary">🖨️ طباعة</a>
                                    @endif
                                    @if (!empty($doc['edit_url']))
                                        <a href="{{ $doc['edit_url'] }}" class="btn-action success">✏️ تخصيص الشكل والمحتوى</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</div>
