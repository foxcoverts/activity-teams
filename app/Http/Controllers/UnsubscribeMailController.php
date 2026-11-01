<?php

namespace App\Http\Controllers;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use App\Models\User;
use App\Support\MailPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnsubscribeMailController extends Controller
{
    public function __invoke(Request $request, User $user, MailGroup $group): Response
    {
        abort_unless($group->listUnsubscribe(), 404);
        abort_if($user->trashed() || $user->isSuspended(), 403);

        if ($request->isMethod('post')) {
            $master = $group->masterChannel();
            abort_unless($master instanceof MailChannel, 404);

            MailPreference::save($user, $group, [
                $master->formKey() => '0',
            ]);

            return response(__('Unsubscribed'), 200);
        }

        return response(__('Use the notifications page to change mail preferences.'), 200);
    }
}
