<?php

namespace App\Jobs;

use App\Support\CombinedDigest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCombinedDigest implements ShouldQueue
{
    use Queueable;

    public function handle(CombinedDigest $digest): void
    {
        $digest->sendAll();
    }
}
