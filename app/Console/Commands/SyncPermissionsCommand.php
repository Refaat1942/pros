<?php

namespace App\Console\Commands;

use App\Services\PermissionCatalogService;
use Illuminate\Console\Command;

/**
 * يُشغَّل مع كل نشر: الصفحات/الإجراءات الجديدة تظهر في مصفوفة الصلاحيات وتُمنح لأدوارها الافتراضية.
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'prosthetics:sync-permissions';

    protected $description = 'Add permissions for newly added pages/actions and grant them to their default roles';

    public function handle(PermissionCatalogService $catalog): int
    {
        $added = $catalog->syncNewPermissions();

        if ($added === []) {
            $this->info('الصلاحيات محدّثة — لا جديد.');

            return self::SUCCESS;
        }

        $this->info('صلاحيات جديدة أُضيفت ومُنحت لأدوارها الافتراضية:');
        foreach ($added as $slug) {
            $this->line('  • '.$slug);
        }

        return self::SUCCESS;
    }
}
