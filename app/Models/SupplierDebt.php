<?php

namespace App\Models;

use App\Enums\DebtStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مديونية المورد — تُزاد عند استلام بضاعة (receive).
 */
class SupplierDebt extends Model
{
    protected $fillable = [
        'supplier_id',
        'due',
        'collected',
        'status',
    ];

    protected $casts = [
        'due' => 'decimal:2',
        'collected' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function remaining(): float
    {
        return max(0, round((float) $this->due - (float) $this->collected, 2));
    }

    public function refreshStatus(): void
    {
        $remaining = $this->remaining();
        $due = (float) $this->due;

        $status = match (true) {
            $due <= 0 || $remaining <= 0 => DebtStatus::Paid->value,
            (float) $this->collected > 0 => DebtStatus::Partial->value,
            default => DebtStatus::Pending->value,
        };

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->saveQuietly();
        }
    }
}
