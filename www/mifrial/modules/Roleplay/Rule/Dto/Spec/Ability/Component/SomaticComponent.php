<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Ветка somatic.
 */
final class SomaticComponent implements ActionComponent
{
    /**
     * Создаёт ветку.
     *
     * @param ?string $note Пометка.
     * @param ?int $occupyHands Занятость рук.
     *
     * @return void
     */
    public function __construct(
        private readonly ?string $note,
        private readonly ?int $occupyHands,
    ) {
    }

    /**
     * Дискриминатор.
     *
     * @return string Код.
     */
    public function getType(): string
    {
        return 'somatic';
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

    /**
     * Занятость рук.
     *
     * @return ?int Значение.
     */
    public function getOccupyHands(): ?int
    {
        return $this->occupyHands;
    }
}
