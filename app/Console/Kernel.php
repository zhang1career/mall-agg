<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Create a new console kernel instance.
     */
    public function __construct(Application $app, Dispatcher $events)
    {
        parent::__construct($app, $events);
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void {}

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
