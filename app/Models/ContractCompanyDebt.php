<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مديونية جهة التعاقد — clinic_contract_debts
 */
class ContractCompanyDebt extends Model
{
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'contract_company_id',
        'due',
        'collected',
        'status',
    ];

    protected $casts = [
        'due' => 'decimal:2',
        'collected' => 'decimal:2',
    ];

    public function contractCompany(): BelongsTo
    {
        return $this->belongsTo(ContractCompany::class);
    }
}
