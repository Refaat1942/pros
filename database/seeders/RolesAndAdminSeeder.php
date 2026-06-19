<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

/**
 * الأدوار السبعة + مستخدم اختبار لكل لوحة تحكم.
 */
class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['slug' => Role::SLUG_ADMIN,       'label_ar' => 'مسؤول النظام'],
            ['slug' => Role::SLUG_RECEPTION,   'label_ar' => 'موظف استقبال'],
            ['slug' => Role::SLUG_DOCTOR,      'label_ar' => 'طبيب'],
            ['slug' => Role::SLUG_SPEC,        'label_ar' => 'فني مواصفات'],
            ['slug' => Role::SLUG_ADJUSTMENTS, 'label_ar' => 'فني تعديلات'],
            ['slug' => Role::SLUG_OPERATIONS,  'label_ar' => 'مكتب عمليات'],
            ['slug' => Role::SLUG_TECHNICAL,   'label_ar' => 'مسؤول مخزن'],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['slug' => $data['slug']], ['label_ar' => $data['label_ar']]);
        }

        foreach (Role::ALL_SLUGS as $slug) {
            User::updateOrCreate(
                ['email' => "{$slug}@clinic.local"],
                [
                    'name'     => Role::where('slug', $slug)->value('label_ar'),
                    'password' => UserFactory::TEST_PASSWORD,
                    'role_id'  => Role::where('slug', $slug)->value('id'),
                    'status'   => User::STATUS_ACTIVE,
                ]
            );
        }
    }
}
