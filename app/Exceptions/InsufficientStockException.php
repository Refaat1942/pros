<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُطلق داخل DB::transaction عند عدم كفاية الرصيد أثناء إنشاء BOM.
 * يُلتقط خارج المعاملة لتحديث حالة PricingRequest دون أن يُطرح تعديل الحالة في الـ rollback.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $stockItemCode,
        public readonly int    $required,
        public readonly int    $available,
        public readonly ?int   $pricingRequestId = null,
    ) {
        parent::__construct(
            "الكمية غير كافية للصنف {$stockItemCode} — متاح: {$available}، مطلوب: {$required}"
        );
    }
}
