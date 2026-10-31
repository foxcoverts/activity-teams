<?php

namespace App\Models;

use App\Enums\CalendarMethod;
use App\Enums\InboundMailOutcome;
use App\Support\InboundCalendar;
use Database\Factories\InboundMailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

/**
 * @property-read object $payload
 * @property-read ?string $rawCalendar
 */
class InboundMail extends Model
{
    /** @use HasFactory<InboundMailFactory> */
    use HasFactory, HasUlids;

    protected $fillable = [
        'body',
        'from_address',
        'to_address',
        'subject',
        'calendar_method',
        'outcome',
        'outcome_detail',
        'resource_type',
        'resource_id',
        'handled_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'calendar_method' => CalendarMethod::class,
            'outcome' => InboundMailOutcome::class,
            'handled_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setBodyAttribute(string $value): void
    {
        $json = json_decode($value);

        if ($json === null || $json === false) {
            throw new InvalidArgumentException('Body must be valid JSON.');
        }

        if (! is_object($json) || ! property_exists($json, 'subject')) {
            throw new InvalidArgumentException('Body does not look like an encoded email object.');
        }

        $this->attributes['body'] = $value;
    }

    protected function payload(): Attribute
    {
        return Attribute::make(
            get: fn (): object => json_decode($this->attributes['body'] ?? '{}') ?: (object) [],
        );
    }

    public function calendar(): ?InboundCalendar
    {
        $raw = $this->rawCalendar;

        return $raw === null ? null : InboundCalendar::loadData($raw);
    }

    protected function rawCalendar(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                $attachments = $this->payload->attachments ?? [];

                foreach ($attachments as $attachment) {
                    if (! is_object($attachment)) {
                        continue;
                    }

                    if (($attachment->contentType ?? null) !== 'text/calendar') {
                        continue;
                    }

                    $content = $attachment->content ?? null;

                    if (is_object($content) && ($content->type ?? null) === 'Buffer' && is_array($content->data ?? null)) {
                        $data = '';

                        foreach ($content->data as $byte) {
                            $data .= chr((int) $byte);
                        }

                        return $data;
                    }

                    if (is_string($content) && $content !== '') {
                        return $content;
                    }

                    if (is_string($attachment->content ?? null) && $attachment->content !== '') {
                        return $attachment->content;
                    }
                }

                return null;
            },
        );
    }

    public function markReviewed(): static
    {
        if ($this->reviewed_at === null) {
            $this->reviewed_at = $this->freshTimestamp();
        }

        return $this;
    }

    public function scopeNeedsReview(Builder $query): void
    {
        $query->whereNull('reviewed_at')
            ->where(function (Builder $query): void {
                $query->whereNull('handled_at')
                    ->orWhereIn('outcome', [
                        InboundMailOutcome::Queued->value,
                        InboundMailOutcome::Failed->value,
                    ]);
            });
    }

    public static function extractAddress(?object $field): ?string
    {
        if ($field === null) {
            return null;
        }

        $value = $field->value[0]->address ?? null;

        return is_string($value) && $value !== '' ? strtolower($value) : null;
    }

    public static function summarizeFromPayload(object $payload): array
    {
        return [
            'from_address' => self::extractAddress($payload->from ?? null),
            'to_address' => self::extractAddress($payload->to ?? null),
            'subject' => is_string($payload->subject ?? null) ? $payload->subject : null,
        ];
    }
}
