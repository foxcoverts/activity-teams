<?php

namespace App\Models;

use App\Enums\NewsPostStatus;
use App\Filament\Member\Resources\NewsPosts\NewsPostResource;
use App\Jobs\NotifyDueNewsPosts;
use App\Notifications\NewsPublishedNotification;
use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Override;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class NewsPost extends Model
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'body',
        'team_id',
        'author_id',
        'published_at',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return parent::casts() + [
            'published_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsPost $newsPost): void {
            if (blank($newsPost->slug) && filled($newsPost->title)) {
                $newsPost->slug = static::uniqueSlugFor($newsPost, Str::slug($newsPost->title));
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopeForTeam(Builder $query, Team $team): void
    {
        $query->where('team_id', $team->getKey());
    }

    public function scopeLive(Builder $query): void
    {
        $query->whereNowOrPast('published_at');
    }

    public function scopeForUserFeeds(Builder $query, User $user): void
    {
        $teamIds = $user->teams()->pluck('teams.id');

        $query->whereIn('team_id', $teamIds)
            ->live()
            ->orderByDesc('published_at');
    }

    public function scopeDueForNotification(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('notified_at');
    }

    public function getStatus(): NewsPostStatus
    {
        if (filled($this->deleted_at)) {
            return NewsPostStatus::Deleted;
        }

        if (blank($this->published_at)) {
            return NewsPostStatus::Draft;
        }

        if ($this->published_at > now()) {
            return NewsPostStatus::Scheduled;
        }

        return NewsPostStatus::Published;
    }

    public function isLive(): bool
    {
        return $this->getStatus() === NewsPostStatus::Published;
    }

    public function excerpt(): string
    {
        return Str::of($this->body)
            ->markdown()
            ->stripTags()
            ->squish()
            ->limit(280)
            ->value();
    }

    public function memberUrl(): string
    {
        $this->loadMissing('team');

        return NewsPostResource::getUrl('view', [
            'team' => $this->team,
            'record' => $this,
        ], panel: 'member');
    }

    public function publishNow(): void
    {
        $this->forceFill(['published_at' => now()])->save();

        NotifyDueNewsPosts::dispatch();
    }

    public function scheduleAt(Carbon $publishedAt, bool $confirmPast = false): void
    {
        $publishedAt = $publishedAt->seconds(0);

        if ($publishedAt->lt(now()) && ! $confirmPast) {
            throw new \InvalidArgumentException('Past dates must be confirmed.');
        }

        $values = ['published_at' => $publishedAt];

        if ($publishedAt->lt(now())) {
            $values['notified_at'] = $publishedAt;
        }

        $this->forceFill($values)->save();

        if ($this->notified_at === null) {
            NotifyDueNewsPosts::dispatch();
        }
    }

    public function unpublish(): void
    {
        $this->forceFill(['published_at' => null])->save();
    }

    public function republish(bool $sendUpdate): void
    {
        $this->forceFill(['published_at' => now()])->save();

        if ($sendUpdate) {
            $this->notifyMembers(force: true);
        }
    }

    public function notifyMembers(bool $force = false): void
    {
        if (! $force && $this->notified_at !== null) {
            return;
        }

        if (! $this->isLive()) {
            return;
        }

        $this->loadMissing('team');

        $members = $this->team->members()
            ->where('users.suspended', false)
            ->get();

        Notification::send($members, new NewsPublishedNotification($this));

        $this->forceFill(['notified_at' => now()])->save();
    }

    private static function uniqueSlugFor(NewsPost $newsPost, string $base): string
    {
        $slug = $base !== '' ? $base : 'post';
        $suffix = 1;

        while (static::query()
            ->withTrashed()
            ->where('team_id', $newsPost->team_id)
            ->where('slug', $slug)
            ->when($newsPost->exists, fn (Builder $query): Builder => $query->whereKeyNot($newsPost->getKey()))
            ->exists()
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
