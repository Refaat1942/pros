<?php

namespace App\Models;

use App\Support\ContractBillingSplit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * جهة التعاقد — تأمين، صحي، عسكري، صندوق إعاقة...
 */
class ContractCompany extends Model
{
    protected $fillable = [
        'company_code',
        'name',
        'is_military',
        'is_contracted',
        'discount_percent',
    ];

    protected $casts = [
        'is_military'       => 'boolean',
        'is_contracted'     => 'boolean',
        'discount_percent'  => 'decimal:2',
    ];

    /** @return array{gross_total: float, patient_share: float, company_share: float, company_share_percent: float, patient_share_percent: float} */
    public function billingSplit(float $grossTotal): array
    {
        return ContractBillingSplit::forCompany($this, $grossTotal);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class, 'contract_company_id');
    }

    public function debt(): HasOne
    {
        return $this->hasOne(ContractCompanyDebt::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'company_name', 'name');
    }
}
