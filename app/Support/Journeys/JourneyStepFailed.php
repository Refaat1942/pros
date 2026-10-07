<?php

namespace App\Support\Journeys;

use RuntimeException;

/** خطوة في مسار الحالة التجريبية لم تنجح — الرسالة تقول السبب كما ردّ به النظام. */
class JourneyStepFailed extends RuntimeException {}
