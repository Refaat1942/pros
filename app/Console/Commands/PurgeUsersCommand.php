<?php

namespace App\Console\Commands;

use App\Services\UserPurgeService;
use Illuminate\Console\Command;

/**
 * حذف كل حسابات الموظفين مع الإبقاء على السوبر أدمن.
 */
class PurgeUsersCommand extends Command
{
    protected $signature = 'prosthetics:purge-users
                            {--force : تنفيذ بدون تأكيد}';

    protected $description = 'Delete all user accounts except the super admin — keeps roles, permissions, settings, and audit log';

    public function handle(UserPurgeService $purge): int
    {
        if (! $purge->hasSuperAdmin()) {
            $this->error('لا يوجد حساب سوبر أدمن — لن يتم الحذف حتى لا يُغلق النظام.');

            return self::FAILURE;
        }

        $count = $purge->purgeableQuery()->count();

        if ($count === 0) {
            $this->info('لا يوجد مستخدمون غير السوبر أدمن.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "سيتم حذف {$count} مستخدم. يبقى: السوبر أدمن — الأدوار — الصلاحيات — الإعدادات — سجل الرقابة. متابعة؟",
            false,
        )) {
            $this->warn('تم الإلغاء.');

            return self::SUCCESS;
        }

        $result = $purge->purge();

        $this->info('تم مسح حسابات الموظفين — السوبر أدمن محفوظ.');
        $this->table(['البند', 'العدد'], [
            ['deleted', $result['deleted']],
            ['deactivated', $result['deactivated']],
            ['kept_super_admins', $result['kept_super_admins']],
        ]);

        if ($result['deactivated_usernames'] !== []) {
            $this->warn('حسابات تعذّر حذفها لارتباطها ببيانات قائمة — تم تعطيلها: '
                .implode('، ', $result['deactivated_usernames']));
        }

        return self::SUCCESS;
    }
}
