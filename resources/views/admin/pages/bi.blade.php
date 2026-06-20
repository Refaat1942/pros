<div class="section-view" id="section-bi">
      <div class="panel bi-intro-panel">
        <div class="panel-header">
          <h3>📡 لوحات القيادة وذكاء الأعمال</h3>
          <span class="badge">5 لوحات لحظية</span>
        </div>
        <p class="bi-intro-text">
          مؤشرات إستراتيجية مباشرة من قاعدة البيانات: توزيع مدني/عسكري، SLA، قيمة المخزون (WAC)، أوامر التشغيل، تكاليف الجهات، ومقارنة أسعار الشراء.
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
