<?php

namespace App\Support\Journeys;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Appointment;
use App\Models\Bom;
use App\Models\CaseRecord;
use App\Models\ContractCompany;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Models\Quote;
use App\Models\Role;
use App\Models\StockItem;
use App\Models\User;
use App\Models\VisitType;
use App\Models\WorkshopSection;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * يمرّر حالة كاملة من الاستقبال حتى التسليم عبر نفس مسارات HTTP التي تستخدمها الشاشات —
 * كل خطوة بمستخدم القسم المسؤول عنها — ويسجّل أين توقفت الحالة ولماذا.
 *
 * يُستخدم من اختبار E2E ومن الأمر prosthetics:demo-journeys على السيرفر.
 */
class CaseJourneyRunner
{
    use InteractsWithAuthentication;
    use MakesHttpRequests;

    public const JOURNEY_ENTITY_CONTRACTED = 'entity_contracted';

    public const JOURNEY_ENTITY_NON_CONTRACTED = 'entity_non_contracted';

    public const JOURNEY_CASH = 'cash';

    public const JOURNEY_MILITARY = 'military';

    public const JOURNEY_MILITARY_SERVICES = 'military_services';

    /** @var \Illuminate\Foundation\Application */
    protected $app;

    /** @var list<array{journey: string, step: string, ok: bool, detail: string}> */
    private array $log = [];

    /** @var array<string, User> */
    private array $users = [];

    /**
     * @param  list<array{item: StockItem, qty: float}>  $lines  أصناف التوصيف والصرف
     */
    public function __construct(private readonly array $lines)
    {
        $this->app = app();
        // الطلبات داخلية من نفس العملية — لا جلسة متصفح ولا توكن CSRF.
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /** @return list<string> */
    public static function journeys(): array
    {
        return [
            self::JOURNEY_ENTITY_CONTRACTED,
            self::JOURNEY_ENTITY_NON_CONTRACTED,
            self::JOURNEY_CASH,
            self::JOURNEY_MILITARY,
            self::JOURNEY_MILITARY_SERVICES,
        ];
    }

    public static function label(string $journey): string
    {
        return match ($journey) {
            self::JOURNEY_ENTITY_CONTRACTED => 'جهة متعاقدة',
            self::JOURNEY_ENTITY_NON_CONTRACTED => 'جهة غير متعاقدة',
            self::JOURNEY_CASH => 'مدني نقدي (على نفقته)',
            self::JOURNEY_MILITARY => 'عسكري (صف/جندي)',
            self::JOURNEY_MILITARY_SERVICES => 'عسكري — تصديق إدارة الخدمات (ضابط)',
            default => $journey,
        };
    }

    /** @return list<array{journey: string, step: string, ok: bool, detail: string}> */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * يشغّل مساراً كاملاً. يرجع الحالة لو وصلت «تم التسليم»، وإلا يرمي JourneyStepFailed.
     */
    public function run(string $journey, string $patientName): CaseRecord
    {
        $patient = $this->step($journey, 'الاستقبال — تسجيل المريض', fn () => $this->registerPatient($journey, $patientName));

        $this->step($journey, 'الاستقبال — تحويل للعيادة', function () use ($patient) {
            $appointment = Appointment::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();
            $this->expectOk($this->as('reception')->patchJson("/reception/appointments/{$appointment->id}/status", [
                'status' => Appointment::STATUS_IN_CLINIC,
            ]));
        });

        $case = $this->step($journey, 'الطبيب — الكشف واعتماد التقرير', function () use ($patient) {
            $queue = $this->expectOk($this->as('doctor')->getJson('/doctor/queue/list'));
            $appointmentId = collect($queue->json('data'))->firstWhere('patient_id', $patient->id)['id'] ?? null;
            if (! $appointmentId) {
                throw new JourneyStepFailed('المريض غير ظاهر في قائمة انتظار الطبيب.');
            }

            $this->expectOk($this->as('doctor')->postJson('/doctor/diagnosis', [
                'patient_id' => $patient->id,
                'appointment_id' => $appointmentId,
                'diagnosis' => 'بتر أسفل الركبة — حالة تجريبية',
                'items' => $this->itemPayload(),
                'lock' => true,
            ]));

            return CaseRecord::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();
        });

        $this->expectStage($journey, $case, CaseRecord::STAGE_TECHNICAL);

        $this->step($journey, 'التوصيف — كتابة الأصناف وإرسالها', function () use ($case) {
            $spec = $this->expectOk($this->as('spec')->postJson('/spec/spec', [
                'case_id' => $case->id,
                'items' => $this->itemPayload(),
            ]));
            $this->expectOk($this->as('spec')->postJson('/spec/spec/'.$spec->json('id').'/submit'));
        });

        if ($case->fresh()->stage_key === CaseRecord::STAGE_ADJUSTMENTS) {
            $this->step($journey, 'المعدلات — مراجعة وإنهاء', fn () => $this->expectOk(
                $this->as('adjustments')->postJson("/adjustments/adjustments/{$case->id}/complete")
            ));
        }

        $this->expectStage($journey, $case, CaseRecord::STAGE_COST_CALC);

        $this->step($journey, 'الاعتماد — تأكيد التكلفة', fn () => $this->expectOk(
            $this->as('costing')->postJson("/costing/queue/{$case->id}/confirm")
        ));

        match ($journey) {
            self::JOURNEY_ENTITY_CONTRACTED, self::JOURNEY_ENTITY_NON_CONTRACTED => $this->entityApproval($journey, $case),
            self::JOURNEY_CASH => $this->cashPayment($journey, $case),
            self::JOURNEY_MILITARY_SERVICES => $this->servicesApproval($journey, $case),
            default => null,
        };

        if ($case->fresh()->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $this->step($journey, 'التشغيل — إصدار أمر الشغل', fn () => $this->expectOk(
                $this->as('operations')->postJson("/operations/pending/{$case->id}/approve")
            ));
        }

        $this->expectStage($journey, $case, CaseRecord::STAGE_MANUFACTURING);

        $this->step($journey, 'قسم الإنتاج — تخصيص القسم والفني واعتماده', function () use ($case) {
            [$sectionId, $technicianId] = $this->workshopAssignee();
            $body = ['workshop_section_id' => $sectionId, 'assigned_technician_id' => $technicianId];
            $this->expectOk($this->as('workshop')->postJson("/workshop/workshop/{$case->id}/assign", $body));
            $this->expectOk($this->as('workshop')->postJson("/workshop/workshop/{$case->id}/approve-assignment", $body));
        });

        $this->step($journey, 'المخزن — صرف الخامات بالباركود', function () use ($case) {
            $bom = Bom::query()->where('case_id', $case->id)->latest('id')->firstOrFail();
            $response = $this->as('technical')->postJson("/technical/bom/{$bom->id}/dispense", [
                'dispense_lines' => collect($this->lines)->map(fn (array $l) => [
                    'barcode' => $l['item']->barcode,
                    'qty' => $l['qty'],
                ])->all(),
            ]);

            if ($response->status() === 202) {
                $requestId = $response->json('dispense_request.id');
                $this->expectOk($this->as('admin')->postJson("/admin/dispense-approvals/{$requestId}/approve"));
            } else {
                $this->expectOk($response);
            }

            if ($bom->fresh()->stage !== Bom::STAGE_WIP) {
                throw new JourneyStepFailed('الصرف لم يُنفَّذ — قائمة المواد ما زالت «خام».');
            }
        });

        $this->step($journey, 'قسم الإنتاج — مراحل التصنيع وإغلاق الجودة', function () use ($case) {
            foreach ([CaseRecord::MFG_GENERATION, CaseRecord::MFG_ASSEMBLY, CaseRecord::MFG_CASTING, CaseRecord::MFG_FINISHING] as $stage) {
                $this->expectOk($this->as('workshop')->postJson("/workshop/workshop/{$case->id}/advance", [
                    'manufacturing_stage' => $stage,
                ]));
            }
            $this->expectOk($this->as('workshop')->postJson("/workshop/workshop/{$case->id}/finish-quality"));
        });

        $this->expectStage($journey, $case, CaseRecord::STAGE_READY_DELIVERY);

        $this->step($journey, 'الاستقبال — التسليم وإغلاق الحالة', fn () => $this->expectOk(
            $this->as('reception')->postJson("/reception/delivery/{$case->id}/confirm")
        ));

        $this->expectStage($journey, $case, CaseRecord::STAGE_DELIVERED);

        return $case->fresh();
    }

    private function entityApproval(string $journey, CaseRecord $case): void
    {
        $quote = $this->step($journey, 'التشغيل — إصدار عرض السعر', function () use ($case) {
            $this->expectOk($this->as('operations')->postJson("/operations/pending/{$case->id}/release-quote"));

            return Quote::query()->where('case_id', $case->id)->latest('id')->firstOrFail();
        });

        $this->step($journey, 'الاستقبال — طباعة العرض وتسجيل خطاب الموافقة', function () use ($case, $quote) {
            $this->expectOk($this->as('reception')->get("/reception/quote/{$quote->id}/print"));
            $case->loadMissing('patient', 'contractCompany');
            $this->expectOk($this->as('reception')->postJson('/reception/approval-letter/confirm', [
                'quote_no' => $quote->quote_no,
                'patient_name' => $case->patient->name,
                'approved_amount' => (float) $quote->fresh()->total,
                'company_name' => $case->contractCompany?->name,
                'letter_ref' => 'LTR-DEMO-'.$case->id,
            ]));
        });
    }

    private function cashPayment(string $journey, CaseRecord $case): void
    {
        // الاعتماد يحوّل حالة الكاش للخزنة تلقائياً؛ إصدار العرض يدوياً فقط لو بقيت في التشغيل.
        if ($case->fresh()->stage_key === CaseRecord::STAGE_OPERATIONS) {
            $this->step($journey, 'التشغيل — إصدار عرض السعر وتحويله للخزنة', fn () => $this->expectOk(
                $this->as('operations')->postJson("/operations/pending/{$case->id}/release-quote")
            ));
        }

        $this->expectStage($journey, $case, CaseRecord::STAGE_CASHIER);

        $this->step($journey, 'الخزنة — تحصيل المبلغ كاش', function () use ($case) {
            $total = (float) Quote::query()->where('case_id', $case->id)->latest('id')->value('total');
            $this->expectOk($this->as('cashier')->postJson("/cashier/payments/{$case->id}/confirm", [
                'method' => 'cash',
                'amount' => $total,
            ]));
        });
    }

    private function servicesApproval(string $journey, CaseRecord $case): void
    {
        $this->expectStage($journey, $case, CaseRecord::STAGE_SERVICES_APPROVAL);

        $this->step($journey, 'إدارة الخدمات — التصديق', fn () => $this->expectOk(
            $this->as('admin')->postJson("/admin/services-approvals/{$case->id}/approve", ['notes' => 'تصديق تجريبي'])
        ));
    }

    private function registerPatient(string $journey, string $name): Patient
    {
        $visitType = VisitType::query()->first() ?? VisitType::query()->create(['name' => 'كشف أولي']);
        $seed = (string) random_int(10_000_000, 99_999_999);

        $body = [
            'name' => $name,
            'visit_type_id' => $visitType->id,
            'phone' => '010'.$seed,
            'national_id' => '2990101'.substr($seed, 0, 7),
        ];

        $body += match ($journey) {
            self::JOURNEY_ENTITY_CONTRACTED => [
                'patient_classification' => 'entity',
                'contract_company_id' => $this->company(contracted: true)->id,
            ],
            self::JOURNEY_ENTITY_NON_CONTRACTED => [
                'patient_classification' => 'entity',
                'contract_company_id' => $this->company(contracted: false)->id,
            ],
            self::JOURNEY_CASH => ['patient_classification' => 'cash'],
            default => [
                'patient_classification' => 'military',
                'military_rank_id' => (MilitaryRank::query()->first() ?? MilitaryRank::query()->create(['name' => 'رقيب']))->id,
                'military_number' => 'M'.$seed,
                'seniority_number' => 'S'.$seed,
                'military_weapon' => 'المشاة',
                'sovereign_entity' => 'القوات المسلحة',
                'military_beneficiary_category' => $journey === self::JOURNEY_MILITARY_SERVICES
                    ? Patient::BENEFICIARY_OFFICER
                    : Patient::BENEFICIARY_ENLISTED,
            ],
        };

        $response = $this->expectOk($this->as('reception')->postJson('/reception/patients', $body));

        // بالمعرّف من رد التسجيل — بالاسم قد يلتقط مريضاً بنفس الاسم من جهاز آخر.
        return Patient::query()->findOrFail((int) $response->json('id'));
    }

    private function company(bool $contracted): ContractCompany
    {
        $existing = ContractCompany::query()
            ->where('is_military', false)
            ->where('is_contracted', $contracted)
            ->first();

        return $existing ?? ContractCompany::query()->create([
            'company_code' => 'DEMO-'.Str::upper(Str::random(5)),
            'name' => $contracted ? 'جهة تجريبية متعاقدة' : 'جهة تجريبية غير متعاقدة',
            'is_military' => false,
            'is_contracted' => $contracted,
        ]);
    }

    /** @return array{0: int, 1: int} */
    private function workshopAssignee(): array
    {
        $options = $this->expectOk($this->as('workshop')->getJson('/workshop/workshop-assignment/options'));

        foreach ((array) $options->json('sections') as $section) {
            $technician = $section['technicians'][0]['id'] ?? null;
            if ($technician) {
                return [(int) $section['id'], (int) $technician];
            }
        }

        throw new JourneyStepFailed('لا يوجد قسم إنتاج نشط به فني — أضف قسماً وفنياً من «الأقسام والفنيين».');
    }

    /** @return list<array{stock_item_code: string, name: string, qty: float}> */
    private function itemPayload(): array
    {
        return collect($this->lines)->map(fn (array $l) => [
            'stock_item_code' => (string) ($l['item']->operationalCode() ?: $l['item']->code),
            'name' => $l['item']->name,
            'qty' => $l['qty'],
        ])->all();
    }

    private function as(string $role): static
    {
        $this->users[$role] ??= self::userFor($role);

        return $this->actingAs($this->users[$role]);
    }

    /** مستخدم القسم إن وُجد، وإلا مدير النظام (يملك كل الصلاحيات). */
    public static function userFor(string $role): User
    {
        $user = User::query()
            ->where('status', 'active')
            ->whereHas('role', fn ($q) => $q->where('slug', $role))
            ->orderBy('id')
            ->first();

        return $user ?? User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', [Role::SLUG_SUPER_ADMIN, Role::SLUG_ADMIN]))
            ->orderByRaw('CASE WHEN role_id = (SELECT id FROM roles WHERE slug = ?) THEN 0 ELSE 1 END', [Role::SLUG_SUPER_ADMIN])
            ->orderBy('id')
            ->firstOrFail();
    }

    private function expectOk(TestResponse $response): TestResponse
    {
        if ($response->status() >= 200 && $response->status() < 300) {
            return $response;
        }

        $message = $response->json('message') ?: Str::limit(strip_tags((string) $response->getContent()), 200);
        $errors = collect((array) $response->json('errors'))->flatten()->implode(' — ');

        throw new JourneyStepFailed(trim("HTTP {$response->status()}: {$message} {$errors}"));
    }

    private function expectStage(string $journey, CaseRecord $case, string $stage): void
    {
        $actual = $case->fresh()->stage_key;
        if ($actual !== $stage) {
            $this->log[] = ['journey' => $journey, 'step' => "المرحلة المتوقعة {$stage}", 'ok' => false, 'detail' => "الحالة في «{$actual}»"];

            throw new JourneyStepFailed("الحالة في «{$actual}» بدلاً من «{$stage}».");
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $action
     * @return T
     */
    private function step(string $journey, string $label, callable $action): mixed
    {
        try {
            $result = $action();
        } catch (\Throwable $e) {
            $this->log[] = ['journey' => $journey, 'step' => $label, 'ok' => false, 'detail' => $e->getMessage()];

            throw $e instanceof JourneyStepFailed ? $e : new JourneyStepFailed($e->getMessage(), previous: $e);
        }

        $this->log[] = ['journey' => $journey, 'step' => $label, 'ok' => true, 'detail' => ''];

        return $result;
    }
}
