<?php

namespace Tests\Feature;

use App\Actions\NormalizeMerchandiseImageAction;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseItemImage;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NormalizeMerchandiseImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_is_resized_and_gets_a_square_thumbnail(): void
    {
        Storage::fake('public');

        $team = Team::factory()->withShop()->create();
        $item = MerchandiseItem::factory()->for($team)->create();
        $sourcePath = 'merchandise/'.$team->getKey().'/source.jpg';

        Storage::disk('public')->put($sourcePath, $this->jpegBinary(2400, 1200));

        $image = MerchandiseItemImage::factory()->for($item, 'item')->create([
            'path' => $sourcePath,
            'thumb_path' => null,
            'sort' => 0,
        ]);

        $image->refresh();

        $this->assertNotSame($sourcePath, $image->path);
        $this->assertNotNull($image->thumb_path);
        $this->assertTrue(Storage::disk('public')->exists($image->path));
        $this->assertTrue(Storage::disk('public')->exists($image->thumb_path));
        $this->assertFalse(Storage::disk('public')->exists($sourcePath));

        $full = imagecreatefromstring(Storage::disk('public')->get($image->path));
        $this->assertNotFalse($full);
        $this->assertSame(1600, imagesx($full));
        $this->assertSame(800, imagesy($full));
        imagedestroy($full);

        $thumb = imagecreatefromstring(Storage::disk('public')->get($image->thumb_path));
        $this->assertNotFalse($thumb);
        $this->assertSame(NormalizeMerchandiseImageAction::ThumbEdge, imagesx($thumb));
        $this->assertSame(NormalizeMerchandiseImageAction::ThumbEdge, imagesy($thumb));
        imagedestroy($thumb);

        $this->assertStringContainsString('.thumb.jpg', (string) $image->thumbnailUrl());
    }

    public function test_small_images_are_not_upscaled(): void
    {
        Storage::fake('public');

        $team = Team::factory()->withShop()->create();
        $item = MerchandiseItem::factory()->for($team)->create();
        $sourcePath = 'merchandise/'.$team->getKey().'/small.jpg';

        Storage::disk('public')->put($sourcePath, $this->jpegBinary(400, 300));

        $image = MerchandiseItemImage::factory()->for($item, 'item')->create([
            'path' => $sourcePath,
            'thumb_path' => null,
        ]);

        $image->refresh();

        $full = imagecreatefromstring(Storage::disk('public')->get($image->path));
        $this->assertNotFalse($full);
        $this->assertSame(400, imagesx($full));
        $this->assertSame(300, imagesy($full));
        imagedestroy($full);
    }

    private function jpegBinary(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        $this->assertNotFalse($canvas);
        $colour = imagecolorallocate($canvas, 40, 120, 200);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $colour);

        ob_start();
        imagejpeg($canvas, null, 90);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);

        return $binary;
    }
}
