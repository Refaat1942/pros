<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** قيد مستحق على جهة تعاقد بتاريخه — موجب عند ترحيل حالة، سالب عند إشعار دائن. */
class ContractDebtAccrual extends Model
{
    protected $fillable = [
        'contract_company_debt_id',
        'case_id',
        'amount',
        'accrued_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'accrued_at' => 'datetime',
    ];

    public function debt(): BelongsTo
    {
        return $this->belongsTo(ContractCompanyDebt::class, 'contract_company_debt_id');
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }
}
