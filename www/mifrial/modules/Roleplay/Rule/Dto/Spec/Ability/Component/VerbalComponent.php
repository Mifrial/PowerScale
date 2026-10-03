<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Ветка verbal.
 */
final class VerbalComponent implements ActionComponent
{
    /**
     * Создаёт ветку.
     *
     * @param ?string $note Пометка.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $note,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'verbal';
    }

    /**
     * Пометка.
     *
     * @return ?string Значение.
     */
    public function getNote(): ?string
    {
        return $this->note;
    }
}
