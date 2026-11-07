<?php

namespace Tests\Unit;

use App\Enums\BrandColor;
use App\Support\Avatars;
use PHPUnit\Framework\TestCase;

class AvatarsTest extends TestCase
{
    public function test_initials_path_url_has_no_query_string_for_gravatar_defaults(): void
    {
        $url = Avatars::initialsPathUrl('Ada Lovelace', BrandColor::Blue->hex(), 200, rounded: true);

        $this->assertStringStartsWith('https://ui-avatars.com/api/', $url);
        $this->assertStringNotContainsString('?', $url);
        $this->assertStringContainsString('/'.BrandColor::Blue->hex().'/', $url);
        $this->assertStringEndsWith('/png', $url);
    }
}
