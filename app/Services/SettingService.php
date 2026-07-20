<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SettingService
{
    public const KEY_TECHNICAL_CHECK = 'technical_check_rate';

    public const KEY_COMPONENTS_INTEGRATION = 'components_integration_rate';

    public const KEY_MACHINERY_DEPRECIATION = 'machinery_depreciation_rate';

    public const KEY_REHABILITATION_ASSESSMENT = 'rehabilitation_assessment_rate';

    public const KEY_ORG_HEADER_LINES = 'org_header_lines';

    public const KEY_ORG_CENTER_NAME = 'org_center_name';

    public const KEY_ORG_LOGO_PATH = 'org_logo_path';

    public const KEY_NOTIFICATION_SOUND_ENABLED = 'notification_sound_enabled';

    public const KEY_NOTIFICATION_REMINDER_MINUTES = 'notification_reminder_minutes';

    private const DEFAULT_NOTIFICATION_REMINDER_MINUTES = 1;

    /** @var array<string, float> */
    private const DEFAULT_OVERHEAD_RATES = [
        self::KEY_TECHNICAL_CHECK => 30.0,
        self::KEY_COMPONENTS_INTEGRATION => 25.0,
        self::KEY_MACHINERY_DEPRECIATION => 23.0,
        self::KEY_REHABILITATION_ASSESSMENT => 22.0,
    ];

    /** @var list<string> */
    private const DEFAULT_ORG_HEADER_LINES = [
        'وزارة الدفاع',
        'مركز الطب الطبيعي والتأهيلي',
        'وعلاج الروماتيزم ق.م',
        'مصنع الأجهزة التعويضية',
    ];

    private const DEFAULT_ORG_CENTER_NAME = 'مركز الأطراف الصناعية';

    private const DEFAULT_ORG_LOGO_PATH = 'assets/images/org-logo.png';

    /** @return array<string, float> */
    public function overheadRates(): array
    {
        return Cache::rememberForever('settings.overhead_rates', function () {
            $stored = Setting::query()
                ->whereIn('key', array_keys(self::DEFAULT_OVERHEAD_RATES))
                ->pluck('value', 'key');

            $rates = [];

            foreach (self::DEFAULT_OVERHEAD_RATES as $key => $default) {
                $rates[$key] = round((float) ($stored[$key] ?? $default), 2);
            }

            return $rates;
        });
    }

    /** @param array<string, float|int|string> $rates */
    public function updateOverheadRates(array $rates): void
    {
        foreach (self::DEFAULT_OVERHEAD_RATES as $key => $default) {
            if (! array_key_exists($key, $rates)) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) round((float) $rates[$key], 2)],
            );
        }

        Cache::forget('settings.overhead_rates');
    }

    public function overheadRatesSum(): float
    {
        return round(array_sum($this->overheadRates()), 2);
    }

    /** @return list<array{key: string, label: string, rate: float}> */
    public function overheadRateDefinitions(): array
    {
        $rates = $this->overheadRates();

        return [
            [
                'key' => self::KEY_TECHNICAL_CHECK,
                'label' => 'تكاليف الفحص الفني والمطابقة الحركية',
                'rate' => $rates[self::KEY_TECHNICAL_CHECK],
            ],
            [
                'key' => self::KEY_COMPONENTS_INTEGRATION,
                'label' => 'تكاليف دمج المكونات والمفاصل الذكية',
                'rate' => $rates[self::KEY_COMPONENTS_INTEGRATION],
            ],
            [
                'key' => self::KEY_MACHINERY_DEPRECIATION,
                'label' => 'مصروفات إهلاك الآلات والمعدات',
                'rate' => $rates[self::KEY_MACHINERY_DEPRECIATION],
            ],
            [
                'key' => self::KEY_REHABILITATION_ASSESSMENT,
                'label' => 'رسوم التقييم والتأهيل الحركي',
                'rate' => $rates[self::KEY_REHABILITATION_ASSESSMENT],
            ],
        ];
    }

    /**
     * بيانات العلامة الرسمية للمطبوعات (أسطر الترويسة + الاسم المختصر + مسار الشعار).
     *
     * @return array{lines: list<string>, center_name: string, logo_path: string}
     */
    public function branding(): array
    {
        return Cache::rememberForever('settings.branding', function () {
            $stored = Setting::query()
                ->whereIn('key', [
                    self::KEY_ORG_HEADER_LINES,
                    self::KEY_ORG_CENTER_NAME,
                    self::KEY_ORG_LOGO_PATH,
                ])
                ->pluck('value', 'key');

            $linesRaw = $stored[self::KEY_ORG_HEADER_LINES] ?? null;
            $lines = is_string($linesRaw) && trim($linesRaw) !== ''
                ? array_values(array_filter(array_map(
                    'trim',
                    preg_split('/\r\n|\r|\n/', $linesRaw) ?: []
                ), static fn ($line) => $line !== ''))
                : self::DEFAULT_ORG_HEADER_LINES;

            if ($lines === []) {
                $lines = self::DEFAULT_ORG_HEADER_LINES;
            }

            $centerName = trim((string) ($stored[self::KEY_ORG_CENTER_NAME] ?? ''));
            $logoPath = trim((string) ($stored[self::KEY_ORG_LOGO_PATH] ?? ''));

            return [
                'lines' => $lines,
                'center_name' => $centerName !== '' ? $centerName : self::DEFAULT_ORG_CENTER_NAME,
                'logo_path' => $logoPath !== '' ? $logoPath : self::DEFAULT_ORG_LOGO_PATH,
            ];
        });
    }

    /** @param array<string, string|null> $data */
    public function updateBranding(array $data): void
    {
        $map = [
            self::KEY_ORG_HEADER_LINES => $data['lines'] ?? null,
            self::KEY_ORG_CENTER_NAME => $data['center_name'] ?? null,
            self::KEY_ORG_LOGO_PATH => $data['logo_path'] ?? null,
        ];

        foreach ($map as $key => $value) {
            if ($value === null) {
                continue;
            }

            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget('settings.branding');
    }

    public function brandingLogoExists(?string $path): bool
    {
        if ($path === null || trim($path) === '') {
            return false;
        }

        $path = trim($path);
        if (str_starts_with($path, 'storage/')) {
            return Storage::disk('public')->exists(substr($path, strlen('storage/')));
        }

        return is_file(public_path($path));
    }

    public function storeUploadedLogo(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

        if (! in_array($ext, $allowed, true)) {
            throw new \InvalidArgumentException('صيغة الشعار غير مدعومة — استخدم PNG أو JPG أو WEBP أو SVG.');
        }

        Storage::disk('public')->makeDirectory('branding');

        foreach (Storage::disk('public')->files('branding') as $old) {
            if (str_starts_with(basename($old), 'logo.')) {
                Storage::disk('public')->delete($old);
            }
        }

        $filename = 'logo.'.$ext;
        Storage::disk('public')->putFileAs('branding', $file, $filename);

        return 'storage/branding/'.$filename;
    }

    /**
     * إعدادات التنبيه الصوتي للإشعارات (يتحكم فيها السوبر أدمن).
     *
     * @return array{sound_enabled: bool, reminder_minutes: int}
     */
    public function notificationAlerts(): array
    {
        return Cache::rememberForever('settings.notification_alerts', function () {
            $stored = Setting::query()
                ->whereIn('key', [
                    self::KEY_NOTIFICATION_SOUND_ENABLED,
                    self::KEY_NOTIFICATION_REMINDER_MINUTES,
                ])
                ->pluck('value', 'key');

            $minutes = (int) ($stored[self::KEY_NOTIFICATION_REMINDER_MINUTES] ?? self::DEFAULT_NOTIFICATION_REMINDER_MINUTES);
            $minutes = max(1, min(60, $minutes));

            $enabledRaw = $stored[self::KEY_NOTIFICATION_SOUND_ENABLED] ?? '1';

            return [
                'sound_enabled' => filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN),
                'reminder_minutes' => $minutes,
            ];
        });
    }

    /** @param array{sound_enabled?: bool, reminder_minutes?: int} $data */
    public function updateNotificationAlerts(array $data): void
    {
        if (array_key_exists('sound_enabled', $data)) {
            Setting::updateOrCreate(
                ['key' => self::KEY_NOTIFICATION_SOUND_ENABLED],
                ['value' => ($data['sound_enabled'] ?? false) ? '1' : '0'],
            );
        }

        if (array_key_exists('reminder_minutes', $data)) {
            $minutes = max(1, min(60, (int) $data['reminder_minutes']));
            Setting::updateOrCreate(
                ['key' => self::KEY_NOTIFICATION_REMINDER_MINUTES],
                ['value' => (string) $minutes],
            );
        }

        Cache::forget('settings.notification_alerts');
    }
}
