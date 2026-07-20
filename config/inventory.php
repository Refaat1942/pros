<?php

return [
    /** عند true: صرف BOM يمر بطلب معلّق يحتاج اعتماد admin قبل الخصم الفعلي. */
    'dispense_requires_approval' => env('INVENTORY_DISPENSE_REQUIRES_APPROVAL', true),

    /** السماح برفع مستندات فواتير الوارد. */
    'inbound_document_upload' => env('INVENTORY_INBOUND_DOCUMENT_UPLOAD', true),
];
