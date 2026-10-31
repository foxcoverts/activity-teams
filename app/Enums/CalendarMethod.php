<?php

namespace App\Enums;

enum CalendarMethod: string
{
    case Add = 'ADD';
    case Cancel = 'CANCEL';
    case Counter = 'COUNTER';
    case DeclineCounter = 'DECLINECOUNTER';
    case Publish = 'PUBLISH';
    case Refresh = 'REFRESH';
    case Reply = 'REPLY';
    case Request = 'REQUEST';
}
