<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جهاز مستخدم — يحمل FCM token لإرسال الإشعارات المستهدفة.
 */
class UserDevice extends Model
{
    public const TYPE_WEB = 'web';

    public const TYPE_ANDROID = 'android';

    public const TYPE_IOS = 'ios';

    protected $fillable = [
        'user_id',
        'device_id',
        'device_type',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
