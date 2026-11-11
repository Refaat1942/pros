<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\OfflineEnvironmentResetService;
use App\Services\PatientDataPurgeService;
use App\Services\SelectivePatientPurgeService;
use App\Services\StockCatalogPurgeService;
use App\Services\UserPurgeService;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * «حذف البيانات» — مدير النظام فقط، وكل عملية بتأكيد كلمة المرور ونسخة احتياطية قبلها.
 */
class DataPurgeController extends Controller
{
    public const TARGETS = [
        'patients-all' => 'كل المرضى وحالاتهم',
        'stock-catalog' => 'كتالوج الأصناف وحركات المخزن وطلبات التوريد',
        'users' => 'حسابات الموظفين (عدا مدير النظام)',
        'notifications' => 'كل الإشعارات',
        'everything' => 'كل المرضى + كل الأصناف (بداية جديدة)',
    ];

    public function patients(Request $request): JsonResponse
    {
        $this->assertSuperAdmin();
        $q = trim((string) $request->query('q', ''));

        $rows = DB::table('patients')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('patient_code', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('national_id', 'like', "%{$q}%")))
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'patient_code', 'name', 'patient_type', 'phone', 'registered_at']);

        $caseCounts = DB::table('cases')->whereIn('patient_id', $rows->pluck('id'))
            ->selectRaw('patient_id, COUNT(*) as c')->groupBy('patient_id')->pluck('c', 'patient_id');
        $paid = DB::table('payments')->whereIn('patient_id', $rows->pluck('id'))
            ->selectRaw('patient_id, SUM(amount) as s')->groupBy('patient_id')->pluck('s', 'patient_id');

        return response()->json([
            'total' => DB::table('patients')->count(),
            'data' => $rows->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->patient_code,
                'name' => $p->name,
                'type' => match ($p->patient_type) { 'military' => 'عسكري', default => 'مدني' },
                'phone' => $p->phone,
                'registered_at' => $p->registered_at,
                'cases' => (int) ($caseCounts[$p->id] ?? 0),
                'paid' => round((float) ($paid[$p->id] ?? 0), 2),
            ])->values(),
        ]);
    }

    public function purgePatients(Request $request, SelectivePatientPurgeService $selective): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
            'password' => ['required', 'string'],
            'backup' => ['boolean'],
        ], ['ids.required' => 'اختر مريضاً واحداً على الأقل.', 'password.required' => 'اكتب كلمة المرور للتأكيد.']);

        $this->authorizeAction($data['password'], (bool) ($data['backup'] ?? true));
        $counts = $selective->purgePatients($data['ids']);

        return response()->json([
            'message' => 'تم حذف '.($counts['patients'] ?? 0).' مريض وكل البيانات المرتبطة بهم.',
            'counts' => $counts,
        ]);
    }

    public function purge(Request $request, string $target): JsonResponse
    {
        abort_unless(array_key_exists($target, self::TARGETS), 404);
        $data = $request->validate([
            'password' => ['required', 'string'],
            'backup' => ['boolean'],
        ], ['password.required' => 'اكتب كلمة المرور للتأكيد.']);

        $this->authorizeAction($data['password'], (bool) ($data['backup'] ?? true));

        $counts = match ($target) {
            'patients-all' => app(PatientDataPurgeService::class)->purge(),
            'stock-catalog' => app(StockCatalogPurgeService::class)->purge(),
            'users' => app(UserPurgeService::class)->purge(),
            'notifications' => ['notifications' => AppNotification::query()->delete()],
            'everything' => app(OfflineEnvironmentResetService::class)->reset(),
        };

        if ($target === 'notifications') {
            AuditService::log(action: 'purge', description: 'حذف كل الإشعارات', tag: 'admin', after: $counts);
        }

        return response()->json([
            'message' => 'تم حذف: '.self::TARGETS[$target].'.',
            'counts' => $counts,
        ]);
    }

    private function assertSuperAdmin(): User
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless($user && $user->isSuperAdmin(), 403, 'حذف البيانات من صلاحيات مدير النظام فقط.');

        return $user;
    }

    /** مدير النظام + كلمة المرور الصحيحة + نسخة احتياطية (إن طُلبت) قبل أي حذف. */
    private function authorizeAction(string $password, bool $backup): void
    {
        $user = $this->assertSuperAdmin();
        abort_unless(Hash::check($password, $user->password), 422, 'كلمة المرور غير صحيحة — لم يتم حذف أي شيء.');

        if ($backup && Artisan::call('prosthetics:backup') !== 0) {
            abort(422, 'تعذّر أخذ نسخة احتياطية — لم يتم حذف أي شيء. يمكنك إلغاء خيار النسخة الاحتياطية إذا كنت متأكداً.');
        }
    }
}
