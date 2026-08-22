<?php

namespace App\Jobs;

use App\Services\Notifications\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * H-5: إرسال دفع FCM خارج دورة الطلب.
 *
 * سلوك أوفلاين (الشبكة المحلية): FIREBASE_ENABLED=false افتراضياً، والخدمة تُرجِع
 * فوراً بلا أي اتصال شبكي — فلا يوجد أي اعتماد على الإنترنت. مع QUEUE_CONNECTION=sync
 * (الافتراضي أوفلاين) تُنفَّذ المهمة داخل الطلب كما كان (بلا عامل queue مطلوب).
 *
 * على VPS يُفعِّل FCM مع queue حقيقي (database/redis) + عامل: يصبح الإرسال غير
 * حاجب لدورة الطلب، فيُزال خطر بطء php-fpm عند تعذّر الوصول لخوادم Google.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * H-6: لا يُرسَل الدفع إلا بعد نجاح المعاملة (commit) — يمنع إرسال إشعار عن
     * انتقال جرى التراجع عنه. على sync (أوفلاين) يعمل ضمن الطلب كالمعتاد.
     */
    public bool $afterCommit = true;

    /**
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data
     */
    public function __construct(
        private readonly array $tokens,
        private readonly string $title,
        private readonly string $body,
        private readonly array $data = [],
    ) {}

    public function handle(FirebaseService $firebase): void
    {
        if ($this->tokens === []) {
            return;
        }

        // الخدمة نفسها تتحقق من التفعيل وتتعامل مع الأخطاء بأمان (لا تُلقي).
        $firebase->sendToTokens($this->tokens, $this->title, $this->body, $this->data);
    }
}
