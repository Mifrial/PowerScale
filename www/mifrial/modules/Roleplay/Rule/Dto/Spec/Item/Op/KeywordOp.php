<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Item\Op;

/**
 * Ветка keyword.
 */
final class KeywordOp implements ItemModifierOp
{
    /**
     * Создаёт ветку.
     *
     * @param array $add Добавить.
     * @param array $remove Снять.
     *
     * @return void
     */
    public function __construct(
        private readonly array $add,
        private readonly array $remove,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'keyword';
    }

    /**
     * Добавить.
     *
     * @return array Значение.
     */
    public function getAdd(): array
    {
        return $this->add;
    }

    /**
     * Снять.
     *
     * @return array Значение.
     */
    public function getRemove(): array
    {
        return $this->remove;
    }
}
