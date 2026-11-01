<?php

use App\Jobs\NotifyDueNewsPosts;
use App\Jobs\SendCombinedDigest;
use App\Models\PersonalDataAcknowledgement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('news:notify-due', function () {
    NotifyDueNewsPosts::dispatchSync();
})->purpose('Notify members of due news posts');

Schedule::job(new SendCombinedDigest)->daily()->withoutOverlapping();
Schedule::job(new NotifyDueNewsPosts)->everyMinute()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => PersonalDataAcknowledgement::class])->daily();
Schedule::command('users:purge-deleted')->daily()->withoutOverlapping();
