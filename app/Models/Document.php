<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'notes',
        'team_id',
        'uploader_id',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
    ];

    protected static function booted(): void
    {
        static::saving(function (Document $document): void {
            if ($document->isDirty('file_path') && filled($document->file_path)) {
                $document->fillFileMetadata();
            }
        });

        static::updating(function (Document $document): void {
            if (! $document->isDirty('file_path')) {
                return;
            }

            $previous = $document->getOriginal('file_path');

            if (filled($previous) && $previous !== $document->file_path) {
                Storage::disk('local')->delete($previous);
            }
        });

        static::forceDeleted(function (Document $document): void {
            if (filled($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->getKey());
    }

    public function scopeForUser(Builder $query, User $user): void
    {
        $teamIds = $user->teams()->pluck('teams.id');

        $query->whereIn('team_id', $teamIds);
    }

    public function fillFileMetadata(): void
    {
        $disk = Storage::disk('local');

        $this->file_size = $disk->size($this->file_path);
        $this->file_type = $disk->mimeType($this->file_path) ?: 'application/octet-stream';
    }

    public static function downloadNameFromOriginal(string $originalName): string
    {
        return (string) preg_replace('/[^\w\-. ]+/', '', $originalName);
    }

    public static function titleFromOriginal(string $originalName): string
    {
        return (string) preg_replace('/\..+$/', '', $originalName);
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return parent::casts() + [
            'file_size' => 'integer',
        ];
    }
}
