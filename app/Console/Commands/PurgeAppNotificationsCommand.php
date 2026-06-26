<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use Illuminate\Console\Command;

/**
 * حذف كل إشعارات التطبيق من app_notifications — للصيانة الدورية.
 */
class PurgeAppNotificationsCommand extends Command
{
    protected $signature = 'prosthetics:purge-notifications';

    protected $description = 'Delete all in-app notifications (app_notifications table)';

    public function handle(): int
    {
        $count = AppNotification::query()->count();

        if ($count === 0) {
            $this->info('No notifications to purge.');

            return self::SUCCESS;
        }

        AppNotification::query()->delete();

        $this->info("Purged {$count} notification(s).");

        return self::SUCCESS;
    }
}
