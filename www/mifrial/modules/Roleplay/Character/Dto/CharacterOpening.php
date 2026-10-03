<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto;

/**
 * Результат доступа к листу: строка, секции и зрители владельца.
 */
final class CharacterOpening
{
    /**
     * Создаёт результат доступа.
     *
     * @param CharacterRecord $record Строка.
     * @param bool $forOwner Актор — владелец.
     * @param array $sections Разрешённые коды.
     * @param array $viewers Зрители или пусто.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRecord $record,
        private readonly bool $forOwner,
        private readonly array $sections,
        private readonly array $viewers,
    ) {
    }

    /**
     * Строка персонажа.
     *
     * @return CharacterRecord Record.
     */
    public function getRecord(): CharacterRecord
    {
        return $this->record;
    }

    /**
     * Актор владеет листом.
     *
     * @return bool true для владельца.
     */
    public function isForOwner(): bool
    {
        return $this->forOwner;
    }

    /**
     * Секции, которые можно показать.
     *
     * @return array Коды.
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Зрители. У чужого пусто.
     *
     * @return array Гранты.
     */
    public function getViewers(): array
    {
        return $this->viewers;
    }
}
