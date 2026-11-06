<?php

declare(strict_types=1);

namespace Fixtures\Core\Alpha\Dto;

final class FirstDto
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly bool $active,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
