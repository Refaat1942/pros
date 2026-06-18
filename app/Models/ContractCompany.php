<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'is_military' => 'boolean',
    ];

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
