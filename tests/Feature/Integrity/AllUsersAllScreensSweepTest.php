<?php

namespace Tests\Feature\Integrity;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkshopSection;
use App\Services\CatalogListVisibilityService;
use App\Services\UserPageAccessService;
use App\Support\Journeys\CaseJourneyRunner;
use Illuminate\Support\Facades\Route;
use Tests\Support\ProstheticTestCase;

/**
 * كل دور × كل شكل صلاحيات ممكن (مدير قسم، موظف بصفحة واحدة، قوائم أصناف مقفولة للمستخدم أو للدور،
 * صلاحيات إضافية من أقسام أخرى) يفتح كل شاشة وكل طباعة بلا خطأ 500.
 */
class AllUsersAllScreensSweepTest extends ProstheticTestCase
{
    private const SKIP_PREFIXES = ['_ignition', 'sanctum', 'firebase-messaging-sw.js', 'logout', 'api/'];

    public function test_every_role_and_permission_shape_opens_every_screen_without_server_error(): void
    {
        $this->seedRealisticData();
        $paths = $this->allGetPaths();
        $this->assertNotEmpty($paths);

        $failures = [];
        $rendered = 0;
        $variants = $this->userVariants();
        foreach ($variants as $label => $user) {
            foreach ($paths as $path) {
                $response = $this->actingAs($user->fresh(['role']))->get($path, ['Accept' => 'text/html']);
                if ($response->baseResponse->getStatusCode() === 200) {
                    $rendered++;
                }
                if ($response->baseResponse->getStatusCode() >= 500) {
                    $e = $response->exception;
                    $failures[] = sprintf('%s → %s : %s', $label, $path,
                        $e ? class_basename($e).': '.$e->getMessage().' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine() : 'HTTP '.$response->baseResponse->getStatusCode());
                }
                $json = $this->actingAs($user->fresh(['role']))->getJson($path);
                if ($json->baseResponse->getStatusCode() >= 500) {
                    $e = $json->exception;
                    $failures[] = sprintf('%s → %s [json] : %s', $label, $path,
                        $e ? class_basename($e).': '.$e->getMessage().' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine() : 'HTTP '.$json->baseResponse->getStatusCode());
                }
            }
        }

        fwrite(STDERR, sprintf("\nsweep: %d users × %d paths, %d pages rendered 200\n", count($variants), count($paths), $rendered));
        $this->assertGreaterThan(count($paths) * 5, $rendered, 'الاختبار لا يفتح الصفحات فعلياً');
        $this->assertSame([], array_values(array_unique($failures)), implode("\n", array_unique($failures)));
    }

    /** @return array<string, User> */
    private function userVariants(): array
    {
        $visibility = app(CatalogListVisibilityService::class);
        $pageAccess = app(UserPageAccessService::class);
        $allViews = Permission::pluck('id');
        $listsOff = [
            'sections' => array_fill_keys(array_keys($visibility->sections()), ['enabled' => false]),
            'profiles' => array_fill_keys(array_keys($visibility->profiles()), ['enabled' => false, 'columns' => []]),
        ];

        $variants = [];
        foreach (Role::where('slug', '!=', Role::SLUG_SUPER_ADMIN)->get() as $role) {
            $slug = $role->slug;
            $make = function (string $suffix, array $attrs = []) use ($role, $slug) {
                return User::create(array_merge([
                    'name' => "{$slug} {$suffix}", 'username' => substr("{$slug}_{$suffix}", 0, 40),
                    'password' => 'secret123', 'role_id' => $role->id, 'status' => User::STATUS_ACTIVE,
                ], $attrs));
            };

            $variants["{$slug}: مدير قسم"] = $make('mgr');
            $variants["{$slug}: قوائم الأصناف مقفولة للمستخدم"] = $make('lists_off', ['catalog_list_visibility' => $listsOff]);

            // موظف بصفحة واحدة لكل صفحة (≈90 مستخدم، ~12 دقيقة) — FULL_SWEEP=1 php artisan test --filter=AllUsersAllScreens
            $staffPages = $pageAccess->pagesForRole($role);
            if (! env('FULL_SWEEP')) {
                $staffPages = array_slice($staffPages, 0, 1);
            }
            foreach ($staffPages as $page) {
                $variants["{$slug}: موظف بصفحة {$page['key']}"] = $make('staff_'.$page['key'], [
                    'access_tier' => UserPageAccessService::TIER_DEPARTMENT_STAFF,
                    'allowed_pages' => $pageAccess->normalizeStaffPages($slug, [$page['key']]),
                ]);
            }
        }

        // صلاحيات الأدوار نفسها: كل الصلاحيات + القوائم مقفولة على مستوى الدور (شكل «المخزن» في الأوفلاين).
        $superAdmin = $this->userWithRole('admin');
        $roleInput = ['roles' => [], 'sections' => []];
        foreach (Role::where('slug', '!=', Role::SLUG_SUPER_ADMIN)->pluck('slug') as $slug) {
            foreach (array_keys($visibility->profiles()) as $profile) {
                $roleInput['roles'][$slug][$profile] = ['enabled' => false, 'columns' => []];
            }
            foreach (array_keys($visibility->sections()) as $section) {
                $roleInput['sections'][$section]['roles'][$slug] = ['enabled' => false];
            }
        }
        $this->actingAs($superAdmin)->putJson(route('admin.catalog-list-settings.update'), $roleInput)->assertOk();

        foreach (Role::where('slug', '!=', Role::SLUG_SUPER_ADMIN)->get() as $role) {
            $role->permissions()->sync($allViews->reject(fn ($id) => Permission::find($id)?->slug === 'super-admin')->values());
            $variants["{$role->slug}: كل الصلاحيات + القوائم مقفولة للدور"] = User::create([
                'name' => "{$role->slug} all", 'username' => "{$role->slug}_all", 'password' => 'secret123',
                'role_id' => $role->id, 'status' => User::STATUS_ACTIVE,
            ]);
        }

        return $variants;
    }

    private function seedRealisticData(): void
    {
        foreach (['reception', 'doctor', 'spec', 'adjustments', 'costing', 'operations', 'cashier', 'workshop', 'technical', 'super_admin'] as $slug) {
            $this->userWithRole($slug);
        }
        Role::firstOrCreate(['slug' => Role::SLUG_ADMIN], ['label_ar' => 'مدير']);
        app(\App\Services\PermissionCatalogService::class)->syncToDatabase();
        app(\App\Services\PermissionCatalogService::class)->seedRoleDefaults(true);
        $technician = $this->userWithRole(Role::SLUG_WORKSHOP);
        WorkshopSection::create(['name' => 'قسم', 'code' => 'l', 'sort' => 1, 'active' => true])->technicians()->attach($technician->id);
        $knee = $this->stockItem('1101', qty: 50);
        $knee->update(['uom' => 'عدد', 'price' => 2500]);

        foreach ([CaseJourneyRunner::JOURNEY_CASH, CaseJourneyRunner::JOURNEY_ENTITY_CONTRACTED, CaseJourneyRunner::JOURNEY_MILITARY] as $journey) {
            (new CaseJourneyRunner([['item' => $knee->fresh(), 'qty' => 1.0]]))->run($journey, "مريض {$journey}");
        }
    }

    /** @return list<string> */
    private function allGetPaths(): array
    {
        $ids = [
            'case' => \App\Models\CaseRecord::query()->value('id'),
            'contract' => \App\Models\ApprovalContract::query()->value('id'),
            'stockItem' => \App\Models\StockItem::query()->value('id'),
            'quote' => \App\Models\Quote::query()->value('id'),
            'spec' => \App\Models\TechOrderSpec::query()->value('id'),
            'section' => WorkshopSection::query()->value('id'),
            'bom' => \App\Models\Bom::query()->value('id'),
            'company' => \App\Models\ContractCompany::query()->value('id'),
            'supplier' => \App\Models\Supplier::query()->value('id'),
            'payment' => \App\Models\Payment::query()->value('id'),
            'appointment' => \App\Models\Appointment::query()->value('id'),
            'patient' => \App\Models\Patient::query()->value('id'),
            'role' => Role::query()->value('id'),
            'stockDispenseRequest' => \App\Models\StockDispenseRequest::query()->value('id'),
            'militaryDebt' => class_exists(\App\Models\MilitaryDebt::class) ? \App\Models\MilitaryDebt::query()->value('id') : null,
        ];

        $paths = [];
        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }
            $uri = $route->uri();
            foreach (self::SKIP_PREFIXES as $skip) {
                if (str_starts_with($uri, $skip)) {
                    continue 2;
                }
            }
            $ok = true;
            $path = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($ids, &$ok) {
                $v = $ids[$m[1]] ?? null;
                if ($v === null) {
                    $ok = false;
                }

                return (string) $v;
            }, $uri);
            if ($ok) {
                $paths[] = '/'.ltrim($path, '/');
            }
        }

        return array_values(array_unique($paths));
    }
}
