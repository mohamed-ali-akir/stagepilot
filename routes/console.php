<?php

use Illuminate\Support\Facades\Schedule;

/*
 * StagePilot's daily rhythm (needs `php artisan schedule:work` + `php artisan queue:work`, or a cron in production):
 *  - 08:00 prepare the next applications (websites, emails, verified hooks), never sends anything
 *  - 09:00 read replies, queue today's emails (spaced 1 to 3 min apart), email the summary
 */
Schedule::command('stagepilot:prepare')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('stagepilot:morning')->dailyAt('09:00')->withoutOverlapping();
// 03:00 the public demo account is rebuilt, whatever visitors did with it
Schedule::command('stagepilot:demo-reset')->dailyAt('03:00')->when(fn () => config('stagepilot.demo_enabled'));
