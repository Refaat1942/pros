<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreCompanyRequest;
use App\Http\Requests\Finance\UpdateCompanyRequest;
use App\Http\Requests\Reception\StoreReceptionCompanyRequest;
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
                ->get(['id', 'name', 'company_code', 'is_military', 'is_contracted', 'discount_percent']);

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
                ->orderByDesc('id')
        );

        return response()->json([
            'data'  => $companies,
            'total' => $companies->count(),
        ]);
    }

    /**
     * الاستقبال — إضافة جهة مدنية غير متعاقدة للقائمة (اختيار فوري عند تسجيل المريض).
     */
    public function storeFromReception(StoreReceptionCompanyRequest $request): JsonResponse
    {
        $name = trim($request->validated('name'));

        $existing = ContractCompany::query()
            ->where('name', $name)
            ->where('is_military', false)
            ->first();

        if ($existing) {
            if ($existing->is_contracted) {
                return response()->json([
                    'message' => 'هذه الجهة مسجّلة كمتعاقدة — اخترها من القائمة تحت «متعاقد».',
                ], 422);
            }

            return response()->json([
                'message' => 'الجهة موجودة مسبقاً — تم اختيارها.',
                'data'    => $this->companyLookupPayload($existing),
            ]);
        }

        $company = ContractCompany::create([
            'company_code'     => $this->generateCompanyCode(),
            'name'             => $name,
            'is_military'      => false,
            'is_contracted'    => false,
            'discount_percent' => 0,
        ]);

        AuditService::log(
            action:      'create',
            description: "إضافة جهة غير متعاقدة من الاستقبال — {$company->company_code} — {$company->name}",
            tag:         'reception',
            after:       $company->toArray(),
        );

        return response()->json([
            'message' => 'تمت إضافة الجهة — يمكنك الآن حفظ المريض.',
            'data'    => $this->companyLookupPayload($company),
        ], 201);
    }

    /**
     * إنشاء جهة تعاقد → يُنشئ تلقائياً صف المديونية عبر ContractDebtService.
     */
    public function store(StoreCompanyRequest $request): RedirectResponse|JsonResponse
    {
        $company = DB::transaction(function () use ($request) {
            $company = ContractCompany::create([
                'company_code'     => $this->generateCompanyCode(),
                'name'             => $request->name,
                'is_military'      => $request->boolean('is_military'),
                'is_contracted'    => $request->boolean('is_contracted', true),
                'discount_percent' => $request->input('discount_percent', 0),
            ]);

            if ($company->is_contracted) {
                $this->contractDebtService->initialise($company);
            }

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

        $before = $company->only(['name', 'is_military', 'is_contracted', 'discount_percent']);

        $company->update($request->validated());

        if ($company->is_contracted && ! $company->debt) {
            $this->contractDebtService->initialise($company);
        }

        AuditService::log(
            action:      'update',
            description: "تعديل جهة تعاقد {$company->company_code}",
            tag:         'financial',
            before:      $before,
            after:       $company->only(['name', 'is_military', 'is_contracted', 'discount_percent']),
        );

        return response()->json([
            'message' => 'تم تحديث جهة التعاقد بنجاح.',
            'company' => $company->load('debt'),
        ]);
    }

    /**
     * حذف جهة تعاقد — ممنوع إن وُجدت حالات أو مرضى مرتبطون.
     */
    public function destroy(ContractCompany $company): JsonResponse
    {
        if ($company->patients()->exists() || $company->cases()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف الجهة — مرتبطة بمرضى أو حالات مسجّلة.',
            ], 422);
        }

        DB::transaction(function () use ($company) {
            $before = $company->only(['company_code', 'name', 'is_military']);

            $company->delete();

            AuditService::log(
                action:      'delete',
                description: "حذف جهة تعاقد {$before['name']}",
                tag:         'financial',
                before:      $before,
            );
        });

        return response()->json(['message' => 'تم حذف جهة التعاقد بنجاح.']);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function generateCompanyCode(): string
    {
        $last = ContractCompany::orderByDesc('id')->value('company_code');
        $num  = $last ? ((int) ltrim(substr($last, 3), '0') ?: 0) + 1 : 1;

        return sprintf('CO-%03d', $num);
    }

    /** @return array{id: int, name: string, company_code: string, is_military: bool, is_contracted: bool, discount_percent: float|string|null} */
    private function companyLookupPayload(ContractCompany $company): array
    {
        return [
            'id'               => $company->id,
            'name'             => $company->name,
            'company_code'     => $company->company_code,
            'is_military'      => (bool) $company->is_military,
            'is_contracted'    => (bool) $company->is_contracted,
            'discount_percent' => $company->discount_percent,
        ];
    }
}
