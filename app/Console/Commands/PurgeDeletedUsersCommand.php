<?php

namespace App\Console\Commands;

use App\Actions\PurgeDeletedUsersAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('users:purge-deleted')]
#[Description('Strip contact details from accounts deleted more than 30 days ago')]
class PurgeDeletedUsersCommand extends Command
{
    public function handle(PurgeDeletedUsersAction $purge): int
    {
        $count = $purge->purgeDue();

        $this->info(__('Purged :count deleted account(s).', ['count' => $count]));

        return self::SUCCESS;
    }
}
