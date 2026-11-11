<?php

namespace App\Models;

use App\Enums\MailChannel;
use App\Enums\MailGroup;
use Database\Factories\NotificationSettingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationSetting extends Model
{
    /** @use HasFactory<NotificationSettingFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'preferences',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferences' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function group(): MailGroup
    {
        $subject = $this->subject;

        if ($subject instanceof Booking) {
            return MailGroup::Bookings;
        }

        if ($subject instanceof Task) {
            return MailGroup::Tasks;
        }

        return MailGroup::Bookings;
    }

    public function subjectLabel(): string
    {
        $subject = $this->subject;

        if ($subject instanceof Booking) {
            return $subject->relatedLabel();
        }

        return __('Override');
    }

    public function preference(MailChannel $channel): ?bool
    {
        $value = $this->preferences[$channel->value] ?? null;

        if (! is_bool($value)) {
            return null;
        }

        return $value;
    }
}
