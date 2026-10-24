<?php

namespace Tests\Feature;

use App\Filament\Schemas\ContactFields;
use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_the_privacy_notice_is_public_and_rendered_from_markdown(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Privacy Notice')
            ->assertSee('Hinckley and District Scout Council')
            ->assertSee('named shell');
    }

    public function test_privacy_notice_links_use_the_public_page(): void
    {
        config(['app.privacy_notice_url' => null]);

        $this->assertSame(route('privacy'), ContactFields::privacyNoticeUrl());
    }

    public function test_privacy_notice_links_honour_a_configured_override(): void
    {
        config(['app.privacy_notice_url' => 'https://example.org/notice']);

        $this->assertSame('https://example.org/notice', ContactFields::privacyNoticeUrl());
    }
}
