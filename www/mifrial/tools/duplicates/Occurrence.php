<?php

declare(strict_types=1);

namespace Mifrial\Tools\Duplicates;

/**
 * Одно вхождение дублированного участка в конкретном файле.
 */
final class Occurrence
{
    public function __construct(
        public readonly string $path,
        public readonly int $line,
        public readonly int $endLine,
    ) {
    }

    /**
     * @return array{path: string, line: int, endLine: int}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'line' => $this->line,
            'endLine' => $this->endLine,
        ];
    }
}
