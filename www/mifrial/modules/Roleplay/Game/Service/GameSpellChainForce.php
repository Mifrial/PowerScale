<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

/**
 * Остаток успеха каста против Сопротивления магии следующей цели.
 *
 * Цепь тратит РУ проверки первой цели: каждая следующая цель уменьшает
 * остаток на своё Сопротивление магии. Отрицательный остаток — цель
 * избегает заклинания, цепь обрывается.
 */
final class GameSpellChainForce
{
    /**
     * Тратит остаток успеха на сопротивление цели.
     *
     * @param int $rating Остаток успеха каста.
     * @param int $resistance Сопротивление магии цели.
     * @param int $penetration Пробитие сопротивления.
     *
     * @return array{rating: int, spend: int, avoided: bool} Остаток, трата и промах.
     */
    public function spend(int $rating, int $resistance, int $penetration): array
    {
        $spend = max(0, $resistance - max(0, $penetration));
        $left = $rating - $spend;

        return ['rating' => $left, 'spend' => $spend, 'avoided' => $left < 0];
    }
}
