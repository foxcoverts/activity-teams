<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final readonly class BounceReport
{
    /**
     * @param  list<string>  $hardCategories
     */
    public function __construct(
        public string $recipient,
        public ?string $messageId,
        public string $category,
        public ?string $action,
        public ?string $message,
        public string $provider,
        public ?string $providerMessageId,
        public CarbonInterface $bouncedAt,
        public array $hardCategories = ['block', 'recipient', 'spam', 'virus'],
    ) {}

    public function isHard(): bool
    {
        return in_array(strtolower($this->category), $this->hardCategories, true);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromNormalized(
        string $recipient,
        ?string $messageId,
        string $category,
        ?string $action,
        ?string $message,
        string $provider,
        ?string $providerMessageId,
        CarbonInterface|string|null $bouncedAt = null,
    ): self {
        return new self(
            recipient: strtolower(trim($recipient)),
            messageId: $messageId,
            category: strtolower(trim($category)) ?: 'unknown',
            action: filled($action) ? (string) $action : null,
            message: filled($message) ? (string) $message : null,
            provider: $provider,
            providerMessageId: filled($providerMessageId) ? (string) $providerMessageId : null,
            bouncedAt: $bouncedAt instanceof CarbonInterface
                ? $bouncedAt
                : (filled($bouncedAt) ? Carbon::parse((string) $bouncedAt) : now()),
        );
    }
}
