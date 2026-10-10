<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Результат явного resource backfill персонажа.
 */
final class ResourceBackfillResult
{
    /**
     * Создаёт результат backfill.
     *
     * @param CharacterRecord $record Актуальная строка.
     * @param string $status initialized, changed, clamped или noop.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRecord $record,
        private readonly string $status,
    ) {
    }

    /**
     * Возвращает строку после операции.
     *
     * @return CharacterRecord Актуальная строка.
     */
    public function getRecord(): CharacterRecord
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
