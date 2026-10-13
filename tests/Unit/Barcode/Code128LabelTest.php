<?php

namespace Tests\Unit\Barcode;

use App\Support\Barcode\Code128;
use PHPUnit\Framework\TestCase;

/**
 * ملصق الباركود يتقري: نفك الـ SVG الناتج ونتأكد أنه يرجع لنفس الكود،
 * وأن كل الخطوط بنفس المقياس (عدد صحيح من نقاط الطابعة 203dpi).
 */
class Code128LabelTest extends TestCase
{
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /** @return array{text: string, units: int, quiet: int} */
    private function decode(string $svg): array
    {
        preg_match('/viewBox="0 0 (\d+) \d+"/', $svg, $vb);
        preg_match_all('/<rect x="(\d+)" y="0" width="(\d+)"/', $svg, $m, PREG_SET_ORDER);
        $runs = [];
        $prevEnd = null;
        foreach ($m as [, $x, $w]) {
            if ($prevEnd !== null) {
                $runs[] = (int) $x - $prevEnd; // مسافة بيضاء
            }
            $runs[] = (int) $w;
            $prevEnd = (int) $x + (int) $w;
        }
        $quiet = (int) $m[0][1];
        $this->assertSame($quiet, (int) $vb[1] - $prevEnd, 'هامش السكون متساوي من الجهتين');

        $codes = [];
        for ($i = 0; $i + 6 <= count($runs); $i += 6) {
            $chunk = implode('', array_slice($runs, $i, 6));
            if (count($runs) - $i === 7) {
                $chunk = implode('', array_slice($runs, $i, 7));
            }
            $code = array_search($chunk, self::PATTERNS, true);
            $this->assertNotFalse($code, "نمط غير صالح {$chunk}");
            $codes[] = $code;
            if ($code === 106) {
                break;
            }
        }

        $this->assertSame(104, $codes[0], 'Start B');
        $this->assertSame(106, end($codes), 'Stop');
        $data = array_slice($codes, 1, -2);
        $sum = 104;
        foreach ($data as $i => $c) {
            $sum += $c * ($i + 1);
        }
        $this->assertSame($sum % 103, $codes[count($codes) - 2], 'checksum');

        return ['text' => implode('', array_map(fn ($c) => chr($c + 32), $data)), 'units' => (int) $vb[1], 'quiet' => $quiet];
    }

    public function test_label_barcode_decodes_and_uses_whole_printer_dots(): void
    {
        $dotMm = 25.4 / 203;
        foreach (['BCI-307', 'BCI-ITM-258', 'BC-RM-001', 'ITM-1101'] as $code) {
            $svg = Code128::svgForLabel($code, 38, 72, 10.5, 2, 14);
            $decoded = $this->decode($svg);
            $this->assertSame($code, $decoded['text']);
            $this->assertGreaterThanOrEqual(10, $decoded['quiet']);

            preg_match('/width="([\d.]+)mm"/', $svg, $w);
            $dotsPerModule = ((float) $w[1] / $dotMm) / $decoded['units'];
            $this->assertEqualsWithDelta(round($dotsPerModule), $dotsPerModule, 0.01, "{$code}: الوحدة عدد صحيح من نقاط الطابعة");
            $this->assertLessThanOrEqual(38 * 0.92 + 0.01, (float) $w[1], "{$code}: داخل الملصق");
        }
    }

    public function test_short_code_gets_two_dot_bars_on_a_38mm_label(): void
    {
        // BCI-307 لا يدخل بنقطتين في 72% → يتمدد حتى 92% بدل النزول لنقطة واحدة يصعب قراءتها.
        $svg = Code128::svgForLabel('BCI-307', 38, 72, 10.5, 2, 14);
        preg_match('/width="([\d.]+)mm"/', $svg, $w);
        $decoded = $this->decode($svg);
        $this->assertEqualsWithDelta(2.0, ((float) $w[1] / (25.4 / 203)) / $decoded['units'], 0.01);
    }

    public function test_screen_barcode_decodes_with_whole_pixel_modules(): void
    {
        $svg = Code128::svgFit('BCI-307', height: 96, moduleWidth: 2.8, maxWidthPx: 720, quietZone: 18);
        $decoded = $this->decode($svg);
        $this->assertSame('BCI-307', $decoded['text']);
        preg_match('/<svg[^>]* width="([\d.]+)"/', $svg, $w);
        $this->assertSame(0.0, fmod((float) $w[1], $decoded['units']), 'عرض الوحدة بكسل صحيح');
    }
}
