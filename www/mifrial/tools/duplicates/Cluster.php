<?php

declare(strict_types=1);

namespace Mifrial\Tools\Duplicates;

/**
 * Один дублированный участок: одинаковое содержимое во всех вхождениях.
 */
final class Cluster
{
    public const KIND_GATE = 'gate';

    public const KIND_BOILERPLATE = 'boilerplate';

    public const KIND_CROSS_MODULE = 'cross-module';

    public const KIND_ALLOWED = 'allowed';

    /**
     * @param list<Occurrence> $occurrences
     */
    public function __construct(
        public readonly int $tokens,
        public readonly int $lines,
        public readonly array $occurrences,
        public readonly string $kind,
        public readonly ?string $reason = null,
    ) {
    }

    /**
     * @return array{tokens: int, lines: int, kind: string, reason: string|null, occurrences: list<array{path: string, line: int, endLine: int}>}
     */
    public function toArray(): array
    {
        return [
            'tokens' => $this->tokens,
            'lines' => $this->lines,
            'kind' => $this->kind,
            'reason' => $this->reason,
            'occurrences' => array_map(
                static fn(Occurrence $occurrence): array => $occurrence->toArray(),
                $this->occurrences,
            ),
        ];
    }
}
