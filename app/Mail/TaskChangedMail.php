<?php

namespace App\Mail;

class TaskChangedMail extends TaskStatusMail
{
    protected function statusHeading(): string
    {
        return __('Task updated');
    }

    protected function statusIntro(): string
    {
        return __('A task assigned to you has changed.');
    }
}
