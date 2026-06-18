<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بند توصية طبية داخل التقرير
 */
class MedicalRecordItem extends Model
{
    protected $fillable = [
        'medical_record_id',
        'stock_item_code',
        'name',
        'qty',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function medicalRecord(): BelongsTo
    {
        return $this->belongsTo(MedicalRecord::class);
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'stock_item_code', 'code');
    }
}
