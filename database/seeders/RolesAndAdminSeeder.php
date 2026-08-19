<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

/**
 * الأدوار + سوبر أدمن (كامل) + مستخدم اختبار لكل لوحة تشغيلية.
 */
class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => Role::SLUG_SUPER_ADMIN, 'label_ar' => 'سوبر أدمن'],
            ['slug' => Role::SLUG_ADMIN,       'label_ar' => 'مسؤول النظام (محدود)'],
            ['slug' => Role::SLUG_RECEPTION,   'label_ar' => 'الاستقبال'],
            ['slug' => Role::SLUG_DOCTOR,      'label_ar' => 'الطبيب'],
            ['slug' => Role::SLUG_SPEC,        'label_ar' => 'التوصيف'],
            ['slug' => Role::SLUG_ADJUSTMENTS, 'label_ar' => 'المعدلات'],
            ['slug' => Role::SLUG_COSTING,     'label_ar' => 'الاعتماد'],
            ['slug' => Role::SLUG_OPERATIONS,  'label_ar' => 'مكتب التشغيل'],
            ['slug' => Role::SLUG_CASHIER,     'label_ar' => 'الخزنة'],
            ['slug' => Role::SLUG_WORKSHOP,    'label_ar' => 'قسم الإنتاج'],
            ['slug' => Role::SLUG_TECHNICAL,   'label_ar' => 'المخزن'],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['slug' => $data['slug']], ['label_ar' => $data['label_ar']]);
        }

        $this->seedPermissions();

        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'سوبر أدمن',
                'password' => UserFactory::TEST_PASSWORD,
                'role_id' => Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id'),
                'status' => User::STATUS_ACTIVE,
            ]
        );

        foreach (Role::ALL_SLUGS as $slug) {
            if ($slug === Role::SLUG_SUPER_ADMIN) {
                continue;
            }

            User::updateOrCreate(
                ['username' => $slug],
                [
                    'name' => Role::where('slug', $slug)->value('label_ar'),
                    'password' => UserFactory::TEST_PASSWORD,
                    'role_id' => Role::where('slug', $slug)->value('id'),
                    'status' => User::STATUS_ACTIVE,
                ]
            );
        }
    }

    private function seedPermissions(): void
    {
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);
    }
}
