<?php

namespace Tests\Support;

use Tests\TestCase;

/**
 * أساس اختبارات المخزون/التسعير — TestCase + مساعدات البيانات (أصناف، موردون، حالات).
 * كانت ثلاثة ملفات اختبار تمتد منه دون وجوده، فتعذّر تحميل مجموعة الاختبارات بالكامل.
 */
abstract class ProstheticTestCase extends TestCase
{
    use ProstheticTestHelper;
}
