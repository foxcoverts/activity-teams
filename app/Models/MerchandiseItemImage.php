<?php

namespace App\Models;

use App\Actions\NormalizeMerchandiseImageAction;
use Database\Factories\MerchandiseItemImageFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MerchandiseItemImage extends Model
{
    /** @use HasFactory<MerchandiseItemImageFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'merchandise_item_id',
        'path',
        'thumb_path',
        'sort',
    ];

    protected static function booted(): void
    {
        static::saved(function (MerchandiseItemImage $image): void {
            if (! $image->wasChanged('path') && ! $image->wasRecentlyCreated) {
                return;
            }

            if (blank($image->path) || ! Storage::disk('public')->exists($image->path)) {
                return;
            }

            app(NormalizeMerchandiseImageAction::class)($image);
        });

        static::deleting(function (MerchandiseItemImage $image): void {
            $disk = Storage::disk('public');

            foreach ([$image->path, $image->thumb_path] as $path) {
                if (filled($path)) {
                    $disk->delete($path);
                }
            }
        });
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    /**
     * Full catalogue image URL (request-rooted).
     */
    public function url(): ?string
    {
        return $this->publicUrl($this->path);
    }

    /**
     * Square card thumbnail; falls back to the full image when missing.
     */
    public function thumbnailUrl(): ?string
    {
        return $this->publicUrl($this->thumb_path) ?? $this->url();
    }

    private function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return url('storage/'.ltrim($path, '/'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
