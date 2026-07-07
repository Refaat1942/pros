<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** كلمة مرور موحّدة لحسابات الاختبار — غيّرها في الإنتاج */
    public const TEST_PASSWORD = '123456';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= self::TEST_PASSWORD,
            'status' => User::STATUS_ACTIVE,
            'role_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * ربط المستخدم بدور لوحة تحكم — username = slug الدور
     */
    public function forRole(string $slug): static
    {
        return $this->state(function () use ($slug) {
            $labels = [
                Role::SLUG_ADMIN => 'مسؤول النظام',
                Role::SLUG_RECEPTION => 'موظف استقبال',
                Role::SLUG_DOCTOR => 'طبيب',
                Role::SLUG_SPEC => 'فني مواصفات',
                Role::SLUG_ADJUSTMENTS => 'فني تعديلات',
                Role::SLUG_COSTING => 'فني تكاليف',
                Role::SLUG_OPERATIONS => 'مكتب عمليات',
                Role::SLUG_WORKSHOP => 'ورشة التصنيع',
                Role::SLUG_TECHNICAL => 'مسؤول مخزن',
            ];

            $role = Role::firstOrCreate(
                ['slug' => $slug],
                ['label_ar' => $labels[$slug] ?? $slug],
            );

            return [
                'role_id' => $role->id,
                'username' => $slug,
                'name' => $role->label_ar,
            ];
        });
    }

    public function admin(): static
    {
        return $this->forRole(Role::SLUG_ADMIN);
    }

    public function reception(): static
    {
        return $this->forRole(Role::SLUG_RECEPTION);
    }

    public function doctor(): static
    {
        return $this->forRole(Role::SLUG_DOCTOR);
    }

    public function spec(): static
    {
        return $this->forRole(Role::SLUG_SPEC);
    }

    public function adjustments(): static
    {
        return $this->forRole(Role::SLUG_ADJUSTMENTS);
    }

    public function costing(): static
    {
        return $this->forRole(Role::SLUG_COSTING);
    }

    public function operations(): static
    {
        return $this->forRole(Role::SLUG_OPERATIONS);
    }

    public function workshop(): static
    {
        return $this->forRole(Role::SLUG_WORKSHOP);
    }

    public function technical(): static
    {
        return $this->forRole(Role::SLUG_TECHNICAL);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => User::STATUS_INACTIVE]);
    }
}
