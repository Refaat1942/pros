<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class CustomDocument extends Model
{
    protected $fillable = [
        'key',
        'group_label',
        'title',
        'description',
        'body_html',
        'reference_path',
        'template_values',
        'is_active',
        'created_by_user_id',
    ];

    protected $casts = [
        'template_values' => 'array',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function referenceUrl(): ?string
    {
        if (! $this->reference_path) {
            return null;
        }

        if (str_starts_with($this->reference_path, 'storage/')) {
            return asset($this->reference_path);
        }

        return Storage::disk('public')->url($this->reference_path);
    }

    public function referenceIsImage(): bool
    {
        if (! $this->reference_path) {
            return false;
        }

        $ext = strtolower(pathinfo($this->reference_path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}
