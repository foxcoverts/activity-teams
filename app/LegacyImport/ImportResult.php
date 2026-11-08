<?php

namespace App\LegacyImport;

final class ImportResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public int $processed = 0,
        public int $skipped = 0,
        public array $messages = [],
    ) {}

    public function merge(self $other): self
    {
        return new self(
            $this->processed + $other->processed,
            $this->skipped + $other->skipped,
            [...$this->messages, ...$other->messages],
        );
    }

    public function addMessage(string $message): void
    {
        $this->messages[] = $message;
    }
}
