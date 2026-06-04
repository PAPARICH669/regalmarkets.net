<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $tz = config('app.timezone', 'Asia/Kuala_Lumpur');

        // Maintenance window open/close
        $schedule->command('maintenance:sync')->dailyAt('00:00')->timezone($tz);
        $schedule->command('maintenance:sync')->dailyAt('07:00')->timezone($tz);

        // After maintenance ends: pay daily commission, then recompute ranks
        $schedule->command('roi:run')->dailyAt('07:01')->timezone($tz)->withoutOverlapping();
        $schedule->command('rank:update')->dailyAt('07:10')->timezone($tz)->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
