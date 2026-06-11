<?php

namespace Exceedone\Exment\Console;

use Illuminate\Console\Command;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Model\Plugin;
use Carbon\Carbon;

class ScheduleCommand extends Command
{
    use CommandTrait;
    use NotifyScheduleTrait;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'exment:schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute Schedule Batch';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->initExmentCommand();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->debugLog('Exment schedule command called.');
        $this->notify();
        $this->backup();
        $this->clearOperationLog();
        $this->pluginBatch();
        return 0;
    }

    // @phpstan-ignore-next-line
    protected function backup()
    {
        if (!boolval(System::backup_enable_automatic())) {
            return;
        }

        $now = Carbon::now();
        $hh = $now->hour;
        if ($hh != System::backup_automatic_hour()) {
            return;
        }

        // set date as minute and second is 0
        $nowHour = Carbon::create($now->year, $now->month, $now->day, $now->hour, 0, 0);

        $last_executed = System::backup_automatic_executed();
        if (!is_nullorempty($last_executed)) {
            $term = System::backup_automatic_term();
            // set date as minute and second is 0
            $last_executed = Carbon::create($last_executed->year, $last_executed->month, $last_executed->day + $term, $last_executed->hour, 0, 0);
            // @phpstan-ignore-next-line
            if ($last_executed->gt($nowHour)) {
                return;
            }
        }

        // get target
        $target = System::backup_target();
        \Artisan::call('exment:backup', !is_nullorempty($target) ? ['--target' => $target, '--schedule' => 1] : []);

        System::backup_automatic_executed($now);
    }

    /**
     * Auto-delete operation logs older than the configured retention period.
     * Runs when the current time satisfies all configured schedule conditions.
     *
     * @return void
     */
    protected function clearOperationLog()
    {
        if (!boolval(System::operation_log_enable_automatic())) {
            return;
        }

        $keepDays = System::operation_log_keep_days();
        if (is_nullorempty($keepDays) || (int)$keepDays <= 0) {
            return;
        }

        $now = Carbon::now();

        // Check day-of-week condition (ISO: 1=Mon, 2=Tue, ..., 7=Sun)
        $week = System::operation_log_automatic_week();
        if (!is_nullorempty($week) && (string)$now->dayOfWeekIso !== (string)$week) {
            return;
        }

        // Check month condition (1–12)
        $month = System::operation_log_automatic_month();
        if (!is_nullorempty($month) && (string)$now->month !== (string)$month) {
            return;
        }

        // Check day-of-month condition (1–31)
        $day = System::operation_log_automatic_day();
        if (!is_nullorempty($day) && (string)$now->day !== (string)$day) {
            return;
        }

        // Check hour condition (0–23)
        $hour = System::operation_log_automatic_hour();
        if (!is_nullorempty($hour) && (string)$now->hour !== (string)$hour) {
            return;
        }

        // Check minute condition (0–59)
        $minute = System::operation_log_automatic_minute();
        if (!is_nullorempty($minute) && (string)$now->minute !== (string)$minute) {
            return;
        }

        $exitCode = \Artisan::call('exment:log-clear', [
            '--keep-days' => (string)(int)$keepDays,
            '--force'     => true,
        ]);

        if ($exitCode === 0) {
            System::operation_log_automatic_executed($now);
        }
    }

    /**
     * Execute Plugin Batch
     *
     * @return void
     */
    protected function pluginBatch()
    {
        $pluginBatches = Plugin::getBatches();

        foreach ($pluginBatches as $pluginBatch) {
            \Artisan::call("exment:batch", ['--uuid' => $pluginBatch->uuid]);
        }
    }


    // @phpstan-ignore-next-line
    protected function debugLog(string $log)
    {
        if (!boolval(config('exment.debugmode_schedule', false))) {
            return;
        }

        \Log::debug($log);
    }
}
