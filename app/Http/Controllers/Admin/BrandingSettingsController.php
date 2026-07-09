<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingSettingsRequest;
use App\Services\AuditService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;

class BrandingSettingsController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    public function update(UpdateBrandingSettingsRequest $request): JsonResponse
    {
        $before = $this->settings->branding();

        $payload = [
            'center_name' => $request->validated('center_name'),
            'lines' => $request->validated('header_lines'),
        ];

        if ($request->hasFile('logo')) {
            $payload['logo_path'] = $this->settings->storeUploadedLogo($request->file('logo'));
        }

        $this->settings->updateBranding($payload);

        $after = $this->settings->branding();

        AuditService::log(
            action: 'update',
            description: 'تحديث الهوية البصرية (الشعار والترويسة)',
            tag: 'admin',
            before: $before,
            after: $after,
        );

        return response()->json([
            'message' => 'تم حفظ الهوية البصرية.',
            'branding' => $after,
        ]);
    }
}
