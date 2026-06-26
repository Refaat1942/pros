<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * صيانة دورية — إشعارات + سجلات (يُستدعى من الجدولة كل 3 أيام).
 */
class HousekeepingCommand extends Command
{
    protected $signature = 'prosthetics:housekeeping';

    protected $description = 'Purge app notifications and clear application log files';

    public function handle(): int
    {
        $this->call('prosthetics:purge-notifications');
        $this->call('prosthetics:purge-logs');

        return self::SUCCESS;
    }
}
