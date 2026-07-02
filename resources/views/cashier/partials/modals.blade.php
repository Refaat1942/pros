<div id="cashierPaymentModal" class="hidden fixed inset-0 z-[200] bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="font-bold text-slate-800">💵 تأكيد استلام المبلغ</h3>
                <p class="text-xs text-slate-500 mt-1" id="cashierPaymentSubtitle">—</p>
            </div>
            <button type="button" id="closeCashierPaymentModal" class="text-2xl text-slate-400 hover:text-slate-600">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1 p-5 space-y-4">
            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">المبلغ (ج.م)</label>
                <input type="number" step="0.01" min="0.01" id="cashierPaymentAmount"
                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">وسيلة الدفع</label>
                <div id="cashierPaymentMethods" class="grid grid-cols-3 gap-2"></div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">رقم العملية (اختياري)</label>
                <input type="text" id="cashierPaymentReference" placeholder="لإنستاباي / فودافون كاش"
                       class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-1">ملاحظات (اختياري)</label>
                <textarea id="cashierPaymentNotes" rows="2"
                          class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/40"></textarea>
            </div>

            <p class="text-xs text-slate-500 bg-slate-50 rounded-lg p-3 leading-relaxed">
                بعد التأكيد يتحول الطلب تلقائياً إلى المخزن لصرف المواد بالباركود للورشة.
            </p>
        </div>

        <div class="px-5 py-4 border-t border-slate-100 flex items-center justify-end gap-2">
            <button type="button" id="btnCancelCashierPayment"
                    class="rounded-xl border border-slate-300 text-slate-600 px-4 py-2 text-sm font-bold hover:bg-slate-50">إلغاء</button>
            <button type="button" id="btnSubmitCashierPayment"
                    class="rounded-xl bg-emerald-600 text-white px-5 py-2 text-sm font-bold hover:bg-emerald-700">✓ تأكيد الاستلام</button>
        </div>
    </div>
</div>
