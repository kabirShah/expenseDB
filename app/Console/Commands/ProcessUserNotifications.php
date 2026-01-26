<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessUserNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::with('preferences')->get();
        foreach ($users as $user) {
            $prefs = $user->preferences;
            if (!$prefs || $prefs->notification_frequency === 'none') continue;

            // Run checks here
            // Push to FCM / OneSignal later
        }
    }
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('notify:users')->hourly();
    }

}
