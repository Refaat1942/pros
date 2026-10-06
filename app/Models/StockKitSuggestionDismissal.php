<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockKitSuggestionDismissal extends Model
{
    protected $fillable = [
        'scope',
        'signature',
        'item_codes',
        'case_count',
        'dismissed_by_user_id',
    ];

    protected $casts = [
        'item_codes' => 'array',
        'case_count' => 'integer',
    ];
}
