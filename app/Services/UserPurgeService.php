<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * مسح إداري لكل حسابات الموظفين مع الإبقاء على السوبر أدمن فقط.
 *
 * - الأدوار والصلاحيات والإعدادات لا تُمس.
 * - سجل الرقابة لا يُحذف — يحتفظ باسم المستخدم (user_name) حتى بعد حذف الحساب.
 * - كل مستخدم يُحذف في savepoint مستقل: لو قيد مفتاح أجنبي (أو مشغّل حماية سجل
 *   الرقابة على محرك يُطلق المشغّلات مع الـ cascade) منع الحذف، يُعطَّل الحساب بدل
 *   الحذف ولا تفشل العملية كلها.
 */
class UserPurgeService
{
    public function superAdminsQuery(): Builder
    {
        return User::query()->where(function (Builder $q) {
            $q->where('username', 'superadmin')
                ->orWhereHas('role', fn (Builder $r) => $r->where('slug', Role::SLUG_SUPER_ADMIN));
        });
    }

    public function purgeableQuery(): Builder
    {
        $keepIds = $this->superAdminsQuery()->pluck('id');

        return User::query()->whereNotIn('id', $keepIds);
    }

    public function hasSuperAdmin(): bool
    {
        return $this->superAdminsQuery()->exists();
    }

    /**
     * @return array{deleted: int, deactivated: int, kept_super_admins: int, deactivated_usernames: list<string>}
     */
    public function purge(): array
    {
        if (! $this->hasSuperAdmin()) {
            throw new \RuntimeException('لا يوجد حساب سوبر أدمن — تم إيقاف المسح حتى لا يُغلق النظام.');
        }

        $deleted = 0;
        $deactivated = [];

        foreach ($this->purgeableQuery()->orderBy('id')->get() as $user) {
            try {
                DB::transaction(function () use ($user) {
                    $this->deleteUserArtifacts($user->id);
                    DB::table('users')->where('id', $user->id)->delete();
                });
                $deleted++;
            } catch (\Throwable) {
                DB::table('users')->where('id', $user->id)->update([
                    'status' => User::STATUS_INACTIVE,
                    'remember_token' => null,
                ]);
                $this->deleteUserArtifacts($user->id);
                $deactivated[] = (string) $user->username;
            }
        }

        $counts = [
            'deleted' => $deleted,
            'deactivated' => count($deactivated),
            'kept_super_admins' => $this->superAdminsQuery()->count(),
            'deactivated_usernames' => $deactivated,
        ];

        AuditService::log(
            action: 'purge',
            description: 'مسح حسابات الموظفين — الإبقاء على السوبر أدمن فقط',
            tag: 'admin',
            after: $counts,
        );

        return $counts;
    }

    private function deleteUserArtifacts(int $userId): void
    {
        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $userId)
                ->delete();
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $userId)->delete();
        }
    }
}
