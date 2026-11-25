<?php

namespace App\Actions;

use App\Models\MerchandiseItemImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class NormalizeMerchandiseImageAction
{
    public const int MaxLongEdge = 1600;

    public const int ThumbEdge = 600;

    public const int JpegQuality = 82;

    public function __invoke(MerchandiseItemImage $image, string $disk = 'public'): MerchandiseItemImage
    {
        if (blank($image->path)) {
            return $image;
        }

        $storage = Storage::disk($disk);
        $absolute = $storage->path($image->path);

        if (! is_file($absolute)) {
            return $image;
        }

        try {
            $source = $this->loadGdImage($absolute);
        } catch (RuntimeException) {
            // Leave non-image placeholders alone (e.g. tests without binary image data).
            return $image;
        }

        $source = $this->orientFromExif($source, $absolute);

        $full = $this->resizeContain($source, self::MaxLongEdge);
        $thumb = $this->resizeCoverSquare($source, self::ThumbEdge);

        if ($full !== $source) {
            imagedestroy($source);
        }

        $directory = trim((string) Str::beforeLast($image->path, '/'), '/');
        $basename = (string) Str::ulid();
        $fullRelative = ($directory === '' ? '' : $directory.'/').$basename.'.jpg';
        $thumbRelative = ($directory === '' ? '' : $directory.'/').$basename.'.thumb.jpg';

        $this->writeJpeg($storage, $fullRelative, $full);
        $this->writeJpeg($storage, $thumbRelative, $thumb);

        imagedestroy($full);
        imagedestroy($thumb);

        $previousPath = $image->path;
        $previousThumb = $image->thumb_path;

        $image->forceFill([
            'path' => $fullRelative,
            'thumb_path' => $thumbRelative,
        ])->saveQuietly();

        $this->deleteIfDifferent($storage, $previousPath, [$fullRelative, $thumbRelative]);
        $this->deleteIfDifferent($storage, $previousThumb, [$fullRelative, $thumbRelative]);

        return $image->refresh();
    }

    /**
     * @return \GdImage
     */
    private function loadGdImage(string $absolute): object
    {
        $info = @getimagesize($absolute);

        if ($info === false) {
            throw new RuntimeException('The merchandise image could not be read.');
        }

        $image = match ($info[2] ?? null) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolute),
            IMAGETYPE_PNG => @imagecreatefrompng($absolute),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolute),
            IMAGETYPE_GIF => @imagecreatefromgif($absolute),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('The merchandise image format is not supported.');
        }

        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /**
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private function orientFromExif(object $image, string $absolute): object
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($absolute);

        if (! is_array($exif) || ! isset($exif['Orientation'])) {
            return $image;
        }

        $rotated = match ((int) $exif['Orientation']) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function resizeContain(object $source, int $maxLongEdge): object
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $longEdge = max($width, $height);

        if ($longEdge <= $maxLongEdge) {
            return $this->copyImage($source);
        }

        $scale = $maxLongEdge / $longEdge;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        return $this->resample($source, $targetWidth, $targetHeight, 0, 0, $width, $height);
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function resizeCoverSquare(object $source, int $edge): object
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = (int) floor(($width - $side) / 2);
        $srcY = (int) floor(($height - $side) / 2);

        return $this->resample($source, $edge, $edge, $srcX, $srcY, $side, $side);
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function copyImage(object $source): object
    {
        return $this->resample(
            $source,
            imagesx($source),
            imagesy($source),
            0,
            0,
            imagesx($source),
            imagesy($source),
        );
    }

    /**
     * @param  \GdImage  $source
     * @return \GdImage
     */
    private function resample(
        object $source,
        int $targetWidth,
        int $targetHeight,
        int $srcX,
        int $srcY,
        int $srcWidth,
        int $srcHeight,
    ): object {
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate an image canvas.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            $srcX,
            $srcY,
            $targetWidth,
            $targetHeight,
            $srcWidth,
            $srcHeight,
        );

        return $canvas;
    }

    /**
     * @param  \GdImage  $image
     */
    private function writeJpeg(Filesystem $storage, string $relativePath, object $image): void
    {
        ob_start();
        imagejpeg($image, null, self::JpegQuality);
        $binary = (string) ob_get_clean();

        $storage->put($relativePath, $binary);
    }

    /**
     * @param  list<string>  $keep
     */
    private function deleteIfDifferent(Filesystem $storage, ?string $path, array $keep): void
    {
        if (blank($path) || in_array($path, $keep, true)) {
            return;
        }

        $storage->delete($path);
    }
}
