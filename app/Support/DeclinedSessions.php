<?php

namespace App\Support;

use App\Models\Booking;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * Set of declined booking sessions. Each entry is unique by start_at, end_at, and venue_id.
 *
 * @phpstan-type SessionArray array{start_at: ?string, end_at: ?string, venue_id: ?string}
 */
final class DeclinedSessions
{
    /**
     * @param  list<SessionArray>  $sessions
     */
    private function __construct(private array $sessions = []) {}

    public static function empty(): self
    {
        return new self;
    }

    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value === null || $value === '' || $value === []) {
            return self::empty();
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return self::empty();
        }

        $set = self::empty();

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $set = $set->withSession(
                $entry['start_at'] ?? null,
                $entry['end_at'] ?? null,
                $entry['venue_id'] ?? null,
            );
        }

        return $set;
    }

    public static function fromBooking(Booking $booking): self
    {
        return self::empty()->withBooking($booking);
    }

    public function withBooking(Booking $booking): self
    {
        return $this->withSession(
            $booking->start_at,
            $booking->end_at,
            $booking->venue_id,
        );
    }

    public function withSession(mixed $startAt, mixed $endAt, mixed $venueId): self
    {
        $session = self::normaliseSession($startAt, $endAt, $venueId);

        foreach ($this->sessions as $existing) {
            if (self::sessionsEqual($existing, $session)) {
                return $this;
            }
        }

        $copy = $this->sessions;
        $copy[] = $session;

        return new self($copy);
    }

    public function containsBooking(Booking $booking): bool
    {
        $needle = self::normaliseSession(
            $booking->start_at,
            $booking->end_at,
            $booking->venue_id,
        );

        foreach ($this->sessions as $session) {
            if (self::sessionsEqual($session, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->sessions === [];
    }

    /**
     * @return list<SessionArray>
     */
    public function toArray(): array
    {
        return array_values($this->sessions);
    }

    /**
     * @return SessionArray
     */
    private static function normaliseSession(mixed $startAt, mixed $endAt, mixed $venueId): array
    {
        return [
            'start_at' => self::normaliseTimestamp($startAt),
            'end_at' => self::normaliseTimestamp($endAt),
            'venue_id' => self::normaliseVenueId($venueId),
        ];
    }

    private static function normaliseTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->clone()->utc()->format('Y-m-d H:i:s');
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->utc()->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
        }

        return null;
    }

    private static function normaliseVenueId(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @param  SessionArray  $left
     * @param  SessionArray  $right
     */
    private static function sessionsEqual(array $left, array $right): bool
    {
        return $left['start_at'] === $right['start_at']
            && $left['end_at'] === $right['end_at']
            && $left['venue_id'] === $right['venue_id'];
    }
}
