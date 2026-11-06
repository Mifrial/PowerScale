<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Value;

final class Money
{
    public function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {
    }

    public function add(self $other): self
    {
        if ($other->currency !== $this->currency) {
            throw new \RuntimeException('Currency mismatch');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function format(): string
    {
        $units = intdiv($this->amount, 100);
        $cents = abs($this->amount % 100);

        return sprintf('%d.%02d %s', $units, $cents, $this->currency);
    }
}
