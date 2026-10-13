<?php

namespace App\Support\Barcode;

/**
 * مولّد باركود Code 128-B بصيغة SVG — أصلي بالكامل (بدون حزم خارجية).
 *
 * يدعم الأحرف الطباعية ASCII 32..126 (يكفي لأكواد مثل BC-RM-001).
 * يُستخدم لطباعة ملصقات الباركود الحراري في المخزن.
 */
final class Code128
{
    /** أنماط Code128 — كل قيمة سلسلة عرض وحدات (أسود/أبيض بالتناوب). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * يبني SVG للباركود.
     */
    public static function svg(string $data, int $height = 50, float $moduleWidth = 2.0, int $quietZone = 14): string
    {
        $px = max(1, (int) round($moduleWidth));
        $total = self::unitCount($data) + 2 * $quietZone;

        return self::renderSvg($data, $quietZone, (string) ($total * $px), (string) $height, $height);
    }

    /**
     * يولّد SVG لا يتجاوز عرضاً أقصى (بكسل شاشة) — للعرض على الشاشة.
     * عرض الوحدة رقم صحيح من البكسلات حتى تبقى نسب الخطوط (1:2:3:4) مضبوطة.
     */
    public static function svgFit(
        string $data,
        int $height,
        float $moduleWidth,
        float $maxWidthPx,
        int $quietZone = 14,
    ): string {
        $units = self::unitCount($data);
        [$px, $quiet] = self::pickModule($units, max(1, (int) round($moduleWidth)), $maxWidthPx, $quietZone);
        $total = $units + 2 * $quiet;
        $width = $px !== null ? $total * $px : $maxWidthPx;

        return self::renderSvg($data, $quiet, round($width, 2).'', (string) $height, $height);
    }

    /**
     * SVG لملصق طابعة حرارية بمقاس فعلي بالمليمتر — كل وحدة = عدد صحيح من نقاط الطابعة
     * (203dpi افتراضياً). الوحدة الواحدة أقل من نقطتين يصعب على الماسح قراءتها،
     * لذلك نسمح بتجاوز نسبة العرض المطلوبة حتى 92% من عرض الملصق قبل النزول لنقطة واحدة.
     */
    public static function svgForLabel(
        string $data,
        float $labelWidthMm,
        float $preferredWidthPct,
        float $heightMm,
        int $preferredDots = 2,
        int $quietZone = 14,
        int $dpi = 203,
    ): string {
        $dotMm = 25.4 / $dpi;
        $units = self::unitCount($data);
        $preferredDots = max(1, $preferredDots);

        $maxDots = ($labelWidthMm * $preferredWidthPct / 100) / $dotMm;
        [$dots, $quiet] = self::pickModule($units, $preferredDots, $maxDots, $quietZone, minDots: 2);
        if ($dots === null) {
            [$dots, $quiet] = self::pickModule($units, $preferredDots, ($labelWidthMm * 0.92) / $dotMm, $quietZone);
        }

        $total = $units + 2 * $quiet;
        $widthMm = $dots !== null ? $total * $dots * $dotMm : $labelWidthMm * 0.92;
        $heightUnits = 100;

        return self::renderSvg($data, $quiet, round($widthMm, 3).'mm', round($heightMm, 2).'mm', $heightUnits);
    }

    /**
     * أكبر عرض وحدة صحيح يدخل في العرض المتاح؛ يقلّل هامش السكون حتى 10 وحدات (حد المعيار) قبل تصغير الوحدة.
     *
     * @return array{0: int|null, 1: int}
     */
    private static function pickModule(int $units, int $preferred, float $maxWidth, int $quietZone, int $minDots = 1): array
    {
        $quiets = array_values(array_unique([max(10, $quietZone), 10]));
        for ($dots = $preferred; $dots >= $minDots; $dots--) {
            foreach ($quiets as $quiet) {
                if (($units + 2 * $quiet) * $dots <= $maxWidth) {
                    return [$dots, $quiet];
                }
            }
        }

        return [null, 10];
    }

    private static function unitCount(string $data): int
    {
        $count = 0;
        foreach (self::modules($data) as [$moduleUnits]) {
            $count += $moduleUnits;
        }

        return $count;
    }

    /**
     * الرسم بإحداثيات وحدات صحيحة (وحدة = 1 في viewBox) — التكبير موحّد لكل الخطوط فلا تتشوّه النسب.
     */
    private static function renderSvg(string $data, int $quietZone, string $width, string $height, int $heightUnits): string
    {
        $total = self::unitCount($data) + 2 * $quietZone;
        $x = $quietZone;
        $rects = '';

        foreach (self::modules($data) as [$w, $isBar]) {
            if ($isBar) {
                $rects .= '<rect x="'.$x.'" y="0" width="'.$w.'" height="'.$heightUnits.'" fill="#000"/>';
            }
            $x += $w;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height
            .'" viewBox="0 0 '.$total.' '.$heightUnits.'" preserveAspectRatio="none" shape-rendering="crispEdges">'
            .'<rect width="'.$total.'" height="'.$heightUnits.'" fill="#ffffff"/>'
            .$rects.'</svg>';
    }

    /**
     * يحوّل النص إلى قائمة [عرض_الوحدة, هل_هو_شريط_أسود].
     *
     * @return list<array{0:int,1:bool}>
     */
    private static function modules(string $data): array
    {
        $codes = [self::START_B];
        $sum = self::START_B;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $value = ord($data[$i]) - 32;
            if ($value < 0 || $value > 94) {
                $value = 0; // أي حرف غير مدعوم → مسافة
            }
            $codes[] = $value;
            $sum += $value * ($i + 1);
        }

        $codes[] = $sum % 103;   // checksum
        $codes[] = self::STOP;

        $modules = [];

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            $isBar = true; // كل نمط يبدأ بشريط أسود

            for ($j = 0, $plen = strlen($pattern); $j < $plen; $j++) {
                $modules[] = [(int) $pattern[$j], $isBar];
                $isBar = ! $isBar;
            }
        }

        return $modules;
    }
}
