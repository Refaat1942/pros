<div class="bg-pattern"></div>

  <div class="container">
    <header>
      <div class="logo-badge">
        <div class="logo-icon">🦿</div>
        <span>نظام محلي مغلق — Offline First</span>
      </div>
      <h1>نظام الإدارة المتكامل<br><span class="highlight">لمركز إنتاج وتصنيع الأطراف الصناعية</span></h1>
      <p class="subtitle">منصة موحدة لإدارة الأنشطة الإدارية، الطبية، والمخزنية — اختر لوحة التحكم المناسبة لدورك الوظيفي للدخول إلى النظام</p>
      <div class="badge-offline">بيئة تشغيل محلية 100% — بدون اتصال بالإنترنت</div>
    </header>

    <section class="cards-section">
      <p class="section-label">بوابات الدخول حسب الدور الوظيفي</p>
      <div class="cards-grid">

        <a href="{{ route('reception.dashboard') }}" class="role-card reception">
          <div class="icon-wrap">📋</div>
          <h2>الاستقبال</h2>
          <p>تسجيل المرضى والجدولة، إصدار عروض الأسعار مع QR Code، مسح الموافقات، ورفع خطابات الموافقة المالية.</p>
          <span class="enter-btn">
            الدخول للوحة الاستقبال
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('doctor.dashboard') }}" class="role-card doctor">
          <div class="icon-wrap">🩺</div>
          <h2>الطبيب المعالج</h2>
          <p>إدارة قائمة انتظار العيادة، إدخال التوصيات الطبية، وتحويل الحالات رقمياً إلى التوصيف الفني.</p>
          <span class="enter-btn">
            الدخول للوحة العيادة
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('spec.dashboard') }}" class="role-card spec">
          <div class="icon-wrap">📐</div>
          <h2>التوصيف الفني</h2>
          <p>استقبال طلبات العيادة، تحديد الأكواد والكميات، حساب التكلفة، وإرسال الطلبات لاعتماد الإدارة والتسعير.</p>
          <span class="enter-btn">
            الدخول للوحة التوصيف
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('adjustments.dashboard') }}" class="role-card adjustments">
          <div class="icon-wrap">📏</div>
          <h2>المعدلات</h2>
          <p>جدولة تجارب التركيب الأولى والثانية، تسجيل ملاحظات المقاسات، ومتابعة حالات إذن الشغل بعد الورشة.</p>
          <span class="enter-btn">
            الدخول للوحة المعدلات
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('operations.dashboard') }}" class="role-card operations">
          <div class="icon-wrap">🎯</div>
          <h2>التشغيل</h2>
          <p>مكتب التشغيل المركزي — إصدار أوامر الإنتاج، متابعة مراحل BOM، وصرف الخامات بالباركود للمخزن.</p>
          <span class="enter-btn">
            الدخول لمكتب التشغيل
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('technical.dashboard') }}" class="role-card inventory">
          <div class="icon-wrap">📦</div>
          <h2>المخزون</h2>
          <p>إدارة أصناف المخزون والكميات، قوائم BOM، صرف بالباركود، استلام الوارد، وإذن الارتجاع من الورشة.</p>
          <span class="enter-btn">
            الدخول للوحة المخزون
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

        <a href="{{ route('admin.dashboard') }}" class="role-card admin">
          <div class="icon-wrap">⚙️</div>
          <h2>الإدارة</h2>
          <p>التحكم في إعدادات النظام، اعتماد التسعير، التقارير المالية، مديونيات شركات التعاقد، وسجل الرقابة.</p>
          <span class="enter-btn">
            الدخول للوحة الإدارة
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </span>
        </a>

      </div>
    </section>
  </div>

  <footer>
    <p>نموذج تجريبي للعرض — <span class="brand">Fratelanza</span> | نظام إدارة مركز إنتاج الأطراف الصناعية المتكامل</p>
  </footer>