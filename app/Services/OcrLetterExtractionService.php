<?php

namespace App\Services;

use App\Models\Quote;
use App\Support\OcrLetterParser;
use App\Support\QuotePrintPresenter;
use Illuminate\Http\UploadedFile;

/**
 * قراءة خطاب الموافقة (PDF/صورة) واستخراج الحقول للمراجعة البشرية.
 */
class OcrLetterExtractionService
{
    /**
     * @return array{
     *   patient_name: string,
     *   approved_amount: float,
     *   company_name: string,
     *   letter_ref?: string,
     *   letter_date?: string,
     *   ocr_engine: string,
     *   raw_text_length: int,
     * }
     */
    public function extractFromUpload(UploadedFile $file, Quote $quote): array
    {
        $quote->loadMissing(['caseRecord.patient', 'caseRecord.contractCompany']);

        $case    = $quote->caseRecord;
        $patient = $case?->patient;
        $approvedAmount = QuotePrintPresenter::approvedAmount($quote);

        $hints = [
            'patient_hint' => $patient?->name ?? $quote->patient_name,
            'amount_hint'  => $approvedAmount,
            'company_hint' => $case?->company_name ?? $quote->company_name,
        ];

        $defaults = [
            'patient_name'    => $hints['patient_hint'] ?? '',
            'approved_amount' => $hints['amount_hint'],
            'company_name'    => $hints['company_hint'] ?? '',
        ];

        [$rawText, $engine] = $this->readTextFromUpload($file);

        $parsed = OcrLetterParser::parse($rawText, $hints);

        return [
            'patient_name'    => $parsed['patient_name']    ?? $defaults['patient_name'],
            'approved_amount' => $parsed['approved_amount'] ?? $defaults['approved_amount'],
            'company_name'    => $parsed['company_name']    ?? $defaults['company_name'],
            'letter_ref'      => $parsed['letter_ref']      ?? null,
            'letter_date'     => $parsed['letter_date']     ?? null,
            'ocr_engine'      => $engine,
            'raw_text_length' => mb_strlen(OcrLetterParser::normalizeText($rawText)),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function readTextFromUpload(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?? '';
        $path = $file->getRealPath() ?: $file->getPathname();

        if (str_contains($mime, 'pdf') || str_ends_with(strtolower($file->getClientOriginalName()), '.pdf')) {
            return $this->readPdfText($path);
        }

        if (str_starts_with($mime, 'image/')) {
            return $this->readImageText($path);
        }

        return ['', 'unsupported'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function readPdfText(string $path): array
    {
        $pdftotext = config('ocr.pdftotext_path', 'pdftotext');
        if ($this->commandExists($pdftotext)) {
            $cmd  = sprintf('%s -layout -enc UTF-8 %s - 2>NUL', escapeshellarg($pdftotext), escapeshellarg($path));
            $text = shell_exec($cmd) ?? '';

            if (trim($text) !== '') {
                return [$text, 'pdftotext'];
            }
        }

        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $text   = $parser->parseFile($path)->getText();

                if (trim($text) !== '') {
                    return [$text, 'pdfparser'];
                }
            } catch (\Throwable) {
                // fallback below
            }
        }

        $loose = $this->extractLooseTextFromBinary(file_get_contents($path) ?: '');

        return [$loose, $loose !== '' ? 'pdf_binary' : 'none'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function readImageText(string $path): array
    {
        $tesseract = config('ocr.tesseract_path', 'tesseract');
        $lang      = config('ocr.tesseract_lang', 'ara+eng');

        if (! $this->commandExists($tesseract)) {
            return ['', 'none'];
        }

        $outBase = storage_path('app/ocr_tmp/' . uniqid('ocr_', true));
        if (! is_dir(dirname($outBase))) {
            mkdir(dirname($outBase), 0755, true);
        }

        $cmd = sprintf(
            '%s %s %s -l %s 2>NUL',
            escapeshellarg($tesseract),
            escapeshellarg($path),
            escapeshellarg($outBase),
            escapeshellarg($lang),
        );

        shell_exec($cmd);

        $txtFile = $outBase . '.txt';
        $text    = is_file($txtFile) ? (file_get_contents($txtFile) ?: '') : '';

        if (is_file($txtFile)) {
            @unlink($txtFile);
        }

        return [trim($text), trim($text) !== '' ? 'tesseract' : 'none'];
    }

    public function extractLooseTextFromBinary(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $parts = [];

        if (preg_match_all('/[\p{Arabic}][\p{Arabic}\p{N}\s\-\/\.,:؛()]{2,}/u', $bytes, $arabic)) {
            $parts = array_merge($parts, $arabic[0]);
        }

        if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9\s\-\/\.,:]{2,}/', $bytes, $latin)) {
            $parts = array_merge($parts, $latin[0]);
        }

        return OcrLetterParser::normalizeText(implode("\n", array_unique($parts)));
    }

    private function commandExists(string $command): bool
    {
        $check = stripos(PHP_OS_FAMILY, 'Windows') === 0
            ? sprintf('where %s 2>NUL', escapeshellarg($command))
            : sprintf('command -v %s 2>/dev/null', escapeshellarg($command));

        $result = shell_exec($check);

        return is_string($result) && trim($result) !== '';
    }
}
