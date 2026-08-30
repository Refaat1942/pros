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

        // H-10: كلمة مرور السوبر أدمن تُؤخذ من البيئة في الإنتاج (SEED_SUPER_ADMIN_PASSWORD)
        // ولا تُستخدم كلمة مرور الاختبار الضعيفة إلا خارج الإنتاج. لا تُعاد كتابة كلمة
        // المرور إن كان الحساب موجوداً بالفعل (حتى لا يُبطَل تغيير المشرف اليدوي).
        $superAdmin = User::where('username', 'superadmin')->first();
        $superAdminPassword = $this->resolveSuperAdminPassword($superAdmin !== null);

        User::updateOrCreate(
            ['username' => 'superadmin'],
            array_merge(
                [
                    'name' => 'سوبر أدمن',
                    'role_id' => Role::where('slug', Role::SLUG_SUPER_ADMIN)->value('id'),
                    'status' => User::STATUS_ACTIVE,
                ],
                // اضبط كلمة المرور فقط عند توفّرها (إنشاء جديد أو كلمة مرور صريحة).
                $superAdminPassword !== null ? ['password' => $superAdminPassword] : []
            )
        );

        // حسابات اللوحات التشغيلية (demo) — تُبذَر خارج الإنتاج فقط (اختبار/تطوير).
        if (! app()->environment('production')) {
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
    }

    /**
     * كلمة مرور السوبر أدمن:
     *   - إن مُرِّرت SEED_SUPER_ADMIN_PASSWORD تُستخدم دائماً.
     *   - خارج الإنتاج: كلمة مرور الاختبار (للتطوير/الاختبار).
     *   - في الإنتاج بلا متغيّر وبلا حساب قائم: يُوقَف البذر بخطأ واضح (لا كلمة ضعيفة).
     *   - في الإنتاج مع حساب قائم: null (لا تُغيَّر كلمة المرور الحالية).
     */
    private function resolveSuperAdminPassword(bool $accountExists): ?string
    {
        $envPassword = env('SEED_SUPER_ADMIN_PASSWORD');

        if (is_string($envPassword) && $envPassword !== '') {
            return $envPassword;
        }

        if (app()->environment('production')) {
            if (! $accountExists) {
                throw new \RuntimeException(
                    'SEED_SUPER_ADMIN_PASSWORD مطلوب في الإنتاج لإنشاء حساب السوبر أدمن — لن تُستخدم كلمة مرور افتراضية ضعيفة.'
                );
            }

            return null; // حساب قائم — لا تُغيَّر كلمة المرور.
        }

        return UserFactory::TEST_PASSWORD;
    }

    private function seedPermissions(): void
    {
        app(PermissionCatalogService::class)->seedRoleDefaults(fullSync: true);
    }
}
