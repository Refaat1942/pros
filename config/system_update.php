<?php

/**
 * تحديث النظام من المتصفح (مدير النظام) — للنسخة الأوفلاين على ويندوز/Laragon.
 * المفتاح العام بس هنا: بيتحقق من توقيع باكدج التحديث. المفتاح السري عند صاحب النظام فقط.
 */
return [
    'public_key' => env('SYSTEM_UPDATE_PUBLIC_KEY', <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEA6AZZrIKCc2u9o0x6F4B4
gJ+3HtXopr2+cqsE3wUhYZHhwiYajlmLZBbAGE4yvHpF+mb4Rf0ftBg/K1bllN/p
pHKvNSK4t1wEfFjj9HkeabAeKRdSVob/IM91vHHW0zXHYIfC/CjVsew8r50du2+M
dKvR+x6vTJfWy+zvinkb6TzlXk/y1pKn06DRVSQODIjc4WylDgL/Xqig4nwl54ui
7glK33aYZgxZ43rObtqiqRo4ELP7XQpql+it26s4EZ97fOLhdaQC3zzNfVty5u01
lEDEYxHmbLFzSf4pS6nTjsz+uaIzsDHJ1Dhd7uCvvPlwkH4/JsLCmz9wHG3jPFh/
hnnjcUGIH/780cezk2XSf/rMGziMxeTb1pH055fmqEHuP2lpTwxDFGB+B3eqvf+5
2oTzSXHh/6PcTdIadmdu+XwBI4fhYnMLDKGzvWWtO5QBZad4bQ/6bt13AIEXrhSo
VEIjrFXb26U64kedsYK9al/BiXBM/xZxiVh1m37fwa61AgMBAAE=
-----END PUBLIC KEY-----
PEM),

    // حجم الجزء الواحد في الرفع (بايت) — أصغر من حد post_max_size الافتراضي (8M).
    'chunk_bytes' => 4 * 1024 * 1024,

    // أقصى حجم لباكدج التحديث.
    'max_bytes' => 300 * 1024 * 1024,
];
