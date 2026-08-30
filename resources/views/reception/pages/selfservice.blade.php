<div class="tab-content" id="tab-selfservice">
    <div class="panel">
        <div class="panel-header">
            <h3>📱 متابعة حالة الطلب (خدمة ذاتية)</h3>
        </div>
        <div class="panel-body" style="padding:24px;">
            <div class="selfservice-search-hint">
                <div>
                    <strong>ابحث بأي بيانات المريض</strong>
                    <p>
                        الاسم، الهاتف، الرقم القومي، كود المريض، رقم الحالة، مرجع الطلب، أو أمر الشغل —
                        ثم اضغط «استعلام» لمعرفة مرحلة الطلب، ترتيب الطابور، والموعد المتوقع للتسليم.
                    </p>
                </div>
            </div>
            <div class="search-bar" style="margin-top:16px;">
                <input type="search" id="ssInput" autocomplete="off"
                       placeholder="🔍 الاسم، الهاتف، الرقم القومي، كود المريض، رقم الحالة، WO..."
                       aria-label="بحث متابعة حالة الطلب">
                <button type="button" class="btn btn-primary" id="btnSelfService">استعلام</button>
            </div>
            <div id="ssResult"></div>
        </div>
    </div>
</div>
