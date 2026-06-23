<div class="bg-pattern"></div>

<div class="container">
    <header>
        <h1>نظام الإدارة المتكامل<br><span class="highlight">لمركز إنتاج وتصنيع الأطراف الصناعية</span></h1>
        <p class="subtitle">منصة موحدة لإدارة الأنشطة الإدارية، الطبية، والمخزنية — اختر لوحة التحكم المناسبة لدورك الوظيفي للدخول إلى النظام</p>
        
    </header>

    <section class="cards-section">
        <p class="section-label">بوابات الدخول حسب الدور الوظيفي</p>
        <div class="cards-grid">

            <a href="{{ route('dashboard.login', 'reception') }}" class="role-card reception">
                <div class="icon-wrap">📋</div>
                <h2>الاستقبال</h2>
                <p>تسجيل المرضى والجدولة، إصدار عروض الأسعار مع QR Code، مسح الموافقات، ورفع خطابات الموافقة المالية.</p>
                <span class="enter-btn">
                    الدخول للوحة الاستقبال
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'doctor') }}" class="role-card doctor">
                <div class="icon-wrap">🩺</div>
                <h2>الطبيب المعالج</h2>
                <p>إدارة قائمة انتظار العيادة، إدخال التوصيات الطبية، وتحويل الحالات رقمياً إلى التوصيف الفني.</p>
                <span class="enter-btn">
                    الدخول للوحة العيادة
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'spec') }}" class="role-card spec">
                <div class="icon-wrap">📐</div>
                <h2>التوصيف الفني</h2>
                <p>استقبال طلبات العيادة، تحديد الأكواد والكميات، حساب التكلفة، وإرسال الطلبات لاعتماد الإدارة والتسعير.</p>
                <span class="enter-btn">
                    الدخول للوحة التوصيف
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'adjustments') }}" class="role-card adjustments">
                <div class="icon-wrap">📏</div>
                <h2>مكتب المعدلات الفنية / الاستشاري</h2>
                <p>مراجعة بنود التوصيف الفني (للقراءة فقط)، وإضافة مكوّنات استشارية إلى قائمة المواد قبل دفع الحالة لمحرّك التكاليف.</p>
                <span class="enter-btn">
                    الدخول لمكتب المعدلات
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'costing') }}" class="role-card costing">
                <div class="icon-wrap">💰</div>
                <h2>التكاليف</h2>
                <p>لوحة بسيطة ومستقلة — مراجعة تكلفة الحالات الواردة من المعدلات (للقراءة فقط) ثم تأكيد وإصدار عرض السعر.</p>
                <span class="enter-btn">
                    الدخول للوحة التكاليف
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'operations') }}" class="role-card operations">
                <div class="icon-wrap">🎯</div>
                <h2>التشغيل</h2>
                <p>مكتب التشغيل — موافقات عروض الأسعار، طباعة وتسليم العروض للعميل، ومتابعة أوامر الإنتاج والصرف.</p>
                <span class="enter-btn">
                    الدخول لمكتب التشغيل
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'technical') }}" class="role-card inventory">
                <div class="icon-wrap">📦</div>
                <h2>المخزون</h2>
                <p>إدارة أصناف المخزون والكميات، قوائم BOM، صرف بالباركود، استلام الوارد، وإذن الارتجاع من الورشة.</p>
                <span class="enter-btn">
                    الدخول للوحة المخزون
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </span>
            </a>

            <a href="{{ route('dashboard.login', 'admin') }}" class="role-card admin">
                <div class="icon-wrap">⚙️</div>
                <h2>الإدارة</h2>
                <p>التحكم في إعدادات النظام، اعتماد التسعير، التقارير المالية، مديونيات جهات التعاقد، وسجل الرقابة.</p>
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
