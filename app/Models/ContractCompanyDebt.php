<?php

namespace App\Models;

use App\Enums\DebtStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * مديونية جهة التعاقد — clinic_contract_debts
 *
 * هذا الجدول يُكتب حصراً عبر ContractDebtService — لا تُعدِّله مباشرةً.
 */
class ContractCompanyDebt extends Model
{
    public const STATUS_PAID    = DebtStatus::Paid->value;
    public const STATUS_PARTIAL = DebtStatus::Partial->value;
    public const STATUS_PENDING = DebtStatus::Pending->value;

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

    public function collectionEntries(): MorphMany
    {
        return $this->morphMany(DebtCollectionEntry::class, 'payable')->orderBy('installment_no');
    }
}
