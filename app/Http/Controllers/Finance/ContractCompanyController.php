<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCompanyRequest;
use App\Http\Requests\Finance\UpdateCompanyRequest;
use App\Models\ContractCompany;
use App\Services\AuditService;
use App\Services\ContractDebtService;
use App\Traits\PaginationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractCompanyController extends Controller
{
    use PaginationTrait;

    public function __construct(private readonly ContractDebtService $contractDebtService)
    {
    }

    /**
     * قائمة جهات التعاقد مع ملخص المديونية.
     */
    public function index(Request $request): JsonResponse
    {
        // ?all=1 — used by patient registration select dropdown
        if ($request->boolean('all')) {
            $companies = ContractCompany::when(
                    $request->has('is_military'),
                    fn ($q) => $q->where('is_military', $request->boolean('is_military'))
                )
                ->orderBy('name')
                ->get(['id', 'name', 'company_code', 'is_military']);

            return response()->json(['data' => $companies]);
        }

        $companies = $this->fetchForDashboard(
            ContractCompany::with('debt')
                ->when(
                    $request->has('is_military'),
                    fn ($q) => $q->where('is_military', $request->boolean('is_military'))
                )
                ->when(
                    $request->search,
                    fn ($q, $s) => $q->where('name', 'like', "%{$s}%")
                                      ->orWhere('company_code', 'like', "%{$s}%")
                )
                ->orderBy('company_code')
        );

        return response()->json([
            'data'  => $companies,
            'total' => $companies->count(),
        ]);
    }

    /**
     * إنشاء جهة تعاقد → يُنشئ تلقائياً صف المديونية عبر ContractDebtService.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse|JsonResponse
    {
        $company = DB::transaction(function () use ($request) {
            $company = ContractCompany::create([
                'company_code' => $this->generateCompanyCode(),
                'name'         => $request->name,
                'is_military'  => $request->boolean('is_military'),
            ]);

            $this->contractDebtService->initialise($company);

            AuditService::log(
                action:      'create',
                description: "إضافة جهة تعاقد {$company->company_code} — {$company->name}",
                tag:         'financial',
                after:       $company->toArray(),
            );

            return $company;
        });

        if ($request->expectsJson()) {
            return response()->json($company->load('debt'), 201);
        }

        return redirect()
            ->route('admin.companies')
            ->with('success', "تم إضافة جهة التعاقد «{$company->name}» بنجاح.");
    }

    /**
     * تعديل اسم الجهة أو نوعها.
     * is_military لا يمكن تغييره بعد ربط حالات بالجهة.
     */
    public function update(UpdateCompanyRequest $request, ContractCompany $company): JsonResponse
    {
        if ($request->has('is_military') && (bool) $request->is_military !== $company->is_military) {
            if ($company->cases()->exists()) {
                return response()->json([
                    'message' => 'لا يمكن تغيير نوع الجهة (مدنية/عسكرية) بعد ربط حالات بها.',
                ], 422);
            }
        }

        $before = $company->only(['name', 'is_military']);

        $company->update($request->validated());

        AuditService::log(
            action:      'update',
            description: "تعديل جهة تعاقد {$company->company_code}",
            tag:         'financial',
            before:      $before,
            after:       $company->only(['name', 'is_military']),
        );

        return response()->json($company->load('debt'));
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function generateCompanyCode(): string
    {
        $last = ContractCompany::orderByDesc('id')->value('company_code');
        $num  = $last ? ((int) ltrim(substr($last, 3), '0') ?: 0) + 1 : 1;

        return sprintf('CO-%03d', $num);
    }
}
