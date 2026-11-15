<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto;

/**
 * Мир, в который пишется импорт: существующий или ещё не созданный.
 */
final class LoreImportTarget
{
    /**
     * Собирает цель импорта.
     *
     * @param LoreWorldValues $worldValues Мета мира.
     * @param LoreWorldRecord|null $worldRecord Существующий мир.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreWorldValues $worldValues,
        private readonly ?LoreWorldRecord $worldRecord,
    ) {
    }

    /**
     * Мета мира.
     *
     * @return LoreWorldValues Поля.
     */
    public function getWorldValues(): LoreWorldValues
    {
        return $this->worldValues;
    }

    /**
     * Существующий мир.
     *
     * @return LoreWorldRecord|null Мир или null для нового.
     */
    public function getWorldRecord(): ?LoreWorldRecord
    {
        return $this->worldRecord;
    }

    /**
     * Код мира.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->worldValues->getCode();
    }

    /**
     * Мир создаётся импортом.
     *
     * @return bool Флаг.
     */
    public function isNew(): bool
    {
        return $this->worldRecord === null;
    }
}
