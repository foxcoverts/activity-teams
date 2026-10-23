<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserNotificationRouteTest extends TestCase
{
    public function test_route_notification_for_mail(): void
    {
        $user = new User([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->assertSame(['test@example.com' => 'Test User'], $user->routeNotificationForMail());

        $user->email = null;
        $this->assertNull($user->routeNotificationForMail());
    }
}
