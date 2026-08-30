<?php

namespace App\Services\Prices;

class Quote
{
    public function __construct(
        public string $symbol,
        public ?float $price = null,
        public ?float $previousClose = null,
        public ?float $changePct = null,
        public ?string $name = null,
        public ?string $currency = null,
        public ?string $providerId = null,
        public ?string $logoUrl = null,
        public array $meta = [],
    ) {}

    public function resolvedChangePct(): ?float
    {
        if ($this->changePct !== null) {
            return $this->changePct;
        }

        if ($this->price !== null && $this->previousClose) {
            return ($this->price - $this->previousClose) / $this->previousClose * 100;
        }

        return null;
    }
}
