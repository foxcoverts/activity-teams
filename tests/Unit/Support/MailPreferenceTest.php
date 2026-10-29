<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\MailPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_for_keeps_a_stored_token_when_the_column_was_not_retrieved(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['mail_preference_token' => 'keep-this-token'])->save();
        $partial = User::query()->select(['id', 'name', 'email'])->findOrFail($user->getKey());

        $this->assertSame('keep-this-token', MailPreference::tokenFor($partial));
    }

    public function test_token_for_creates_a_token_when_the_column_was_not_retrieved(): void
    {
        $user = User::factory()->create();
        $partial = User::query()->select(['id', 'name', 'email'])->findOrFail($user->getKey());

        $token = MailPreference::tokenFor($partial);

        $this->assertNotSame('', $token);
        $this->assertSame($token, $user->fresh()->mail_preference_token);
    }
}
