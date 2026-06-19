<div class="section-view" id="section-bi">
      <div class="panel" style="margin-bottom:16px;">
        <div class="panel-header">
          <h3>📡 لوحات القيادة وذكاء الأعمال — 5 لوحات لحظية</h3>
        </div>
        <p style="padding:0 20px 14px;margin:0;color:var(--text-muted);font-size:13px;">
          مؤشرات إستراتيجية لحظية: توزيع مدني/عسكري، زمن التنفيذ (SLA)، قيمة المخزون (WAC)، أوامر التشغيل، تكاليف الجهات، ومقارنة WAC ↔ أعلى سعر.
        </p>
      </div>
      <div id="biContent" data-server-rendered="1">
        @isset($board1)
          @include('partials.dashboard-bi', [
            'board1' => $board1,
            'board2' => $board2,
            'board3' => $board3,
            'board4' => $board4,
            'board5' => $board5,
          ])
        @else
          @include('partials.dashboard-bi-empty')
        @endisset
      </div>
    </div>
