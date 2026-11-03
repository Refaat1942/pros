<?php

return [
    /** عند true: صرف BOM يمر بطلب معلّق يحتاج اعتماد admin قبل الخصم الفعلي. */
    'dispense_requires_approval' => env('INVENTORY_DISPENSE_REQUIRES_APPROVAL', true),

    /**
     * اعتماد أدمن المخازن (صلاحية approve-supply-requests / approve-stock-receipts):
     * طلب التوريد من غير صاحب الصلاحية يفضل «بانتظار الاعتماد»، واستلام الوارد يتسجّل طلباً
     * ولا يُضاف للرصيد إلا بعد الاعتماد. صاحب الصلاحية يمشي فوراً زي الأول.
     */
    'supply_requires_approval' => env('INVENTORY_SUPPLY_REQUIRES_APPROVAL', true),
    'receipt_requires_approval' => env('INVENTORY_RECEIPT_REQUIRES_APPROVAL', true),

    /** السماح برفع مستندات فواتير الوارد. */
    'inbound_document_upload' => env('INVENTORY_INBOUND_DOCUMENT_UPLOAD', true),
];
