<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto;

/**
 * Срез ревизии: ревизия и правила в порядке состава.
 */
final class RuleRevisionSlice
{
    /**
     * Создаёт срез.
     *
     * @param RuleRevisionRecord $revisionRecord Ревизия.
     * @param array<int, RuleVersionRecord> $items Правила.
     *
     * @return void
     */
    public function __construct(
        private readonly RuleRevisionRecord $revisionRecord,
        private readonly array $items,
    ) {
    }

    /**
     * Ревизия.
     *
     * @return RuleRevisionRecord Ревизия.
     */
    public function getRevision(): RuleRevisionRecord
    {
        return $this->revisionRecord;
    }

    /**
     * Правила в порядке состава.
     *
     * @return array<int, RuleVersionRecord> Пункты.
     */
    public function getItems(): array
    {
        return $this->items;
    }
}
