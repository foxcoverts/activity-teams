<?php

namespace App\Models;

use App\Contracts\Commentable;
use App\Filament\Teams\Resources\Bookings\BookingResource;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'author_id',
        'body',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function url(): ?string
    {
        $commentable = $this->commentable;

        if (! $commentable instanceof Booking) {
            return null;
        }

        return BookingResource::getUrl('view', ['record' => $commentable], panel: 'teams', tenant: $commentable->team)
            .'?comment='.$this->getKey();
    }

    public function commentTeam(): ?Team
    {
        $commentable = $this->commentable;

        return $commentable instanceof Commentable ? $commentable->commentTeam() : null;
    }

    public function delete()
    {
        return static::withoutTimestamps(fn () => parent::delete());
    }

    public function restore()
    {
        return static::withoutTimestamps(fn () => parent::restore());
    }
}
