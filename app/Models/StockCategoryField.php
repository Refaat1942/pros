<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCategoryField extends Model
{
    protected $fillable = [
        'category_id',
        'field_key',
        'label',
        'type',
        'options',
        'config',
        'sort_order',
        'required',
    ];

    protected $casts = [
        'options'    => 'array',
        'config'     => 'array',
        'sort_order' => 'integer',
        'required'   => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(StockCategory::class, 'category_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(StockItemAttributeValue::class, 'category_field_id');
    }
}
