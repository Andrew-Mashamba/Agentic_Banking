<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily sales summary email at 8:00 AM
        $schedule->command('reports:daily-sales-summary')
            ->dailyAt('08:00')
            ->timezone('UTC')
            ->emailOutputOnFailure(env('MAIL_ERROR_TO', 'admin@example.com'));

        // Clean up old sessions daily at 2:00 AM
        $schedule->command('session:gc')
            ->daily()
            ->at('02:00');

        // Clean up old logs daily at 3:00 AM
        $schedule->command('logs:clean')
            ->dailyAt('03:00')
            ->when(function () {
                return config('app.env') === 'production';
            });

        // Database backup daily at 1:00 AM (requires spatie/laravel-backup)
        // Install with: composer require spatie/laravel-backup
        $schedule->command('backup:run')
            ->dailyAt('01:00')
            ->emailOutputOnFailure(env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_ERROR_TO', 'admin@example.com')));

        // Clean up old backups (keep last 7 days)
        $schedule->command('backup:clean')
            ->dailyAt('01:30');

        // Monitor backup health and send notifications
        $schedule->command('backup:monitor')
            ->dailyAt('09:00');

        // Process failed queue jobs retry (every 30 minutes)
        $schedule->command('queue:retry all')
            ->everyThirtyMinutes()
            ->runInBackground();

        // Clean up failed jobs older than 7 days
        $schedule->command('queue:flush')
            ->weekly()
            ->mondays()
            ->at('04:00');

        // Update cache for frequently accessed data (every hour)
        $schedule->command('cache:prune-stale-tags')
            ->hourly();

        // Generate monthly reports on the first day of each month at 9:00 AM
        $schedule->command('reports:monthly-summary')
            ->monthlyOn(1, '09:00')
            ->emailOutputOnFailure(env('MAIL_ERROR_TO', 'admin@example.com'));

        // Clean up expired orders and reservations daily at 4:00 AM
        $schedule->command('orders:cleanup-expired')
            ->dailyAt('04:00')
            ->runInBackground();

        // Send reminder notifications for upcoming reservations (every 15 minutes)
        $schedule->command('reservations:send-reminders')
            ->everyFifteenMinutes()
            ->runInBackground();

        // Clean up temporary files weekly
        $schedule->command('storage:clean-temp')
            ->weekly()
            ->sundays()
            ->at('05:00');

        // WhatsApp compliance retention
        $schedule->command('whatsapp:prune-conversations')->dailyAt('03:30');
        $schedule->command('whatsapp:prune-pending-tasks')->dailyAt('03:35');
        $schedule->command('whatsapp:prune-attachments')->dailyAt('03:40');
        $schedule->command('whatsapp:prune-memory')->dailyAt('03:45');
        $schedule->command('whatsapp:prune-logs')->dailyAt('03:50');
        $schedule->command('whatsapp:prune-audit-logs')->monthlyOn(1, '04:00');
        $schedule->job(new \App\Jobs\SummarizeConversationToMemory)->dailyAt('04:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
