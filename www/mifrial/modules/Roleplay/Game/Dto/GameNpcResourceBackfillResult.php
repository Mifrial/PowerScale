<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Dto;

/**
 * Результат явного resource backfill NPC.
 */
final class GameNpcResourceBackfillResult
{
    /**
     * Создаёт результат backfill.
     *
     * @param GameNpcRecord $record Актуальная строка NPC.
     * @param string $status initialized, changed или noop.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcRecord $record,
        private readonly string $status,
    ) {
    }

    /**
     * Возвращает NPC после операции.
     *
     * @return GameNpcRecord Актуальная строка.
     */
    public function getRecord(): GameNpcRecord
    {
        return $this->record;
    }

    /**
     * Возвращает статус операции.
     *
     * @return string Статус.
     */
    public function getStatus(): string
    {
        return $this->status;
    }
}
