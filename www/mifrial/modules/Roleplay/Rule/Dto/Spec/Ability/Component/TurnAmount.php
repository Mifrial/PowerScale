<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component;

/**
 * Количество «N ходов»: type turn.
 *
 * Ход — полный лимит ресурса хода; N ходов дают N лимитов.
 */
final class TurnAmount
{
    /**
     * Создаёт количество.
     *
     * @param int $count Ходов.
     *
     * @return void
     */
    public function __construct(private readonly int $count)
    {
    }

    /**
     * Вид количества.
     *
     * @return string Код.
     */
    public function getKind(): string
    {
        return 'turn';
    }

    /**
     * Число ходов.
     *
     * @return int Ходы.
     */
    public function getCount(): int
    {
        return $this->count;
    }
}
