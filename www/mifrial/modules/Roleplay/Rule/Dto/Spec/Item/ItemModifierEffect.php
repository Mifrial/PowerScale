<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item;

/**
 * Эффект модификатора.
 */
final class ItemModifierEffect
{
    /**
     * Создаёт эффект.
     *
     * @param string|null $label Метка.
     * @param string $text Текст.
     * @param array<int, ItemModifierOp> $ops Операции.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $label,
        private readonly string $text,
        private readonly array $ops,
    ) {
    }

    /**
     * Метка.
     *
     * @return string|null Текст или null.
     */
    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * Текст.
     *
     * @return string Текст.
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * Операции.
     *
     * @return array<int, ItemModifierOp> Список.
     */
    public function getOps(): array
    {
        return $this->ops;
    }
}
