<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Ветка material.
 */
final class MaterialComponent implements ActionComponent
{
    /**
     * Создаёт ветку.
     *
     * @param string $mode Режим.
     * @param ?string $itemCode Предмет.
     * @param array $keywordCodes Признаки.
     * @param ?string $description Описание.
     *
     * @return void
     */
    public function __construct(
        private readonly string $mode,
        private readonly ?string $itemCode,
        private readonly array $keywordCodes,
        private readonly ?string $description,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'material';
    }

    /**
     * Режим.
     *
     * @return string Значение.
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Предмет.
     *
     * @return ?string Значение.
     */
    public function getItemCode(): ?string
    {
        return $this->itemCode;
    }

    /**
     * Признаки.
     *
     * @return array Значение.
     */
    public function getKeywordCodes(): array
    {
        return $this->keywordCodes;
    }

    /**
     * Описание.
     *
     * @return ?string Значение.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }
}
