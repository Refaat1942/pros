<?php

namespace Tests\Feature\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\ProstheticTestHelper;
use Tests\TestCase;

/**
 * مصفوفة الوصول بالصلاحيات الافتراضية الفعلية (seedRoleDefaults) — كل قسم يدخل لوحته فقط،
 * ولا ينفّذ إجراءات قسم آخر.
 */
class DepartmentAccessMatrixTest extends TestCase
{
    use ProstheticTestHelper;

    private const DEPARTMENTS = [
        Role::SLUG_RECEPTION, Role::SLUG_DOCTOR, Role::SLUG_SPEC, Role::SLUG_ADJUSTMENTS, Role::SLUG_COSTING,
        Role::SLUG_OPERATIONS, Role::SLUG_CASHIER, Role::SLUG_WORKSHOP, Role::SLUG_TECHNICAL,
    ];

    private const DASHBOARDS = [
        'reception', 'doctor', 'spec', 'adjustments', 'costing', 'operations', 'cashier', 'workshop', 'technical', 'admin',
    ];

    /** إجراءات حساسة لكل قسم — لا يجوز لغيره تنفيذها. */
    private const ACTIONS = [
        Role::SLUG_RECEPTION => ['post', '/reception/patients'],
        Role::SLUG_DOCTOR => ['post', '/doctor/diagnosis'],
        Role::SLUG_SPEC => ['post', '/spec/spec'],
        Role::SLUG_ADJUSTMENTS => ['post', '/adjustments/adjustments/999999/complete'],
        Role::SLUG_COSTING => ['post', '/costing/queue/999999/confirm'],
        Role::SLUG_OPERATIONS => ['post', '/operations/pending/999999/approve'],
        Role::SLUG_CASHIER => ['post', '/cashier/payments/999999/confirm'],
        Role::SLUG_WORKSHOP => ['post', '/workshop/workshop/999999/finish-quality'],
        Role::SLUG_TECHNICAL => ['post', '/technical/bom/999999/dispense'],
    ];

    /** @var array<string, User> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([...self::DEPARTMENTS, Role::SLUG_ADMIN] as $slug) {
            $this->makeRole($slug);
        }
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);

        foreach (self::DEPARTMENTS as $slug) {
            $this->users[$slug] = User::query()->create([
                'name' => 'موظف '.$slug,
                'username' => 'matrix-'.$slug,
                'password' => Hash::make('password'),
                'role_id' => Role::query()->where('slug', $slug)->value('id'),
                'status' => User::STATUS_ACTIVE,
            ]);
        }
    }

    public function test_each_department_opens_only_its_own_dashboard(): void
    {
        $problems = [];

        foreach (self::DEPARTMENTS as $role) {
            foreach (self::DASHBOARDS as $dashboard) {
                $response = $this->actingAs($this->users[$role])->get('/'.$dashboard);
                $status = $response->getStatusCode();
                $landed = $status >= 300 && $status < 400 ? (string) $response->headers->get('Location') : '';
                $own = $dashboard === $role;

                $allowed = $status === 200 || ($landed !== '' && str_contains($landed, '/'.$dashboard.'/'));

                if ($own && ! $allowed) {
                    $problems[] = "{$role} cannot open its own /{$dashboard} ({$status} {$landed})";
                }
                if (! $own && $allowed) {
                    $problems[] = "{$role} CAN open /{$dashboard} ({$status} {$landed})";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_departments_cannot_run_other_departments_actions(): void
    {
        $problems = [];

        // سجلات حقيقية — بمعرّف غير موجود يرد 404 قبل فحص الصلاحية فلا يُختبر شيء.
        $case = $this->caseAtStage($this->cashPatient(), \App\Models\CaseRecord::STAGE_ADJUSTMENTS);
        $bom = \App\Models\Bom::create([
            'case_id' => $case->id,
            'bom_no' => 'BOM-MATRIX-1',
            'order_ref' => $case->order_ref,
            'patient_name' => 'مصفوفة',
            'stage' => \App\Models\Bom::STAGE_RAW,
        ]);

        foreach (self::DEPARTMENTS as $role) {
            foreach (self::ACTIONS as $owner => [$method, $uri]) {
                if ($owner === $role) {
                    continue;
                }

                $uri = str_replace('/technical/bom/999999', '/technical/bom/'.$bom->id, $uri);
                $uri = str_replace('999999', (string) $case->id, $uri);
                $status = $this->actingAs($this->users[$role])->json($method, $uri, [])->getStatusCode();

                // 403 = مرفوض بالصلاحية، 302 = تحويل خارج اللوحة. 404/422 تعني أن الطلب وصل للمنطق.
                if (! in_array($status, [401, 403, 302], true)) {
                    $problems[] = "{$role} → {$method} {$uri} returned {$status} (expected 403)";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_department_users_cannot_open_admin_settings(): void
    {
        $problems = [];
        foreach (self::DEPARTMENTS as $role) {
            foreach (['/admin/employees', '/admin/permissions', '/admin/pathway-settings', '/admin/documents-hub', '/admin/companies', '/admin/costing-settings'] as $uri) {
                $status = $this->actingAs($this->users[$role])->get($uri)->getStatusCode();
                if ($status === 200) {
                    $problems[] = "{$role} CAN open {$uri}";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }
}
