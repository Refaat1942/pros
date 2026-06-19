<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُرمى عند محاولة انتقال workflow غير مسموح للمرحلة الحالية.
 */
class InvalidWorkflowTransitionException extends RuntimeException
{
    public static function forEvent(string $event, string $currentStage): self
    {
        return new self(
            "انتقال غير مسموح: الحدث «{$event}» لا ينطبق على المرحلة الحالية «{$currentStage}»."
        );
    }
}
