<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Interface\Service;

use Closure;
use Mifrial\Roleplay\Mechanic\Dto\CheckRating;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Dto\RollResult;
use Mifrial\Roleplay\Mechanic\Dto\RollSpec;
use Mifrial\Roleplay\Mechanic\Dto\SizedBase;

/**
 * Порт броска: сосед собирает binding и не пишет формулу кубов.
 */
interface IMechanicRolls
{
    /**
     * Бросает кубы на событиях roll.pool, roll.drop и roll.score.
     *
     * @param RollSpec $spec Параметры броска.
     * @param Closure $rng Источник float в [0, 1).
     * @param array<int, MechanicBinding> $bindings Срезы правил.
     * @param array<int, MechanicRecord> $mechanics Строки каталога.
     * @param ResolveActiveOptions $options Фильтр семейства и доп. коды правил.
     *
     * @return RollResult Итог броска.
     */
    public function roll(
        RollSpec $spec,
        Closure $rng,
        array $bindings,
        array $mechanics,
        ResolveActiveOptions $options,
    ): RollResult;

    /**
     * Сравнивает успехи и трудность так же, как проверка на фронте.
     *
     * @param SizedBase $successes Успехи.
     * @param SizedBase $difficulty Трудность.
     * @param int|null $minSize Нижняя граница размера; null — не поднимать.
     *
     * @return CheckRating Прохождение и разность.
     */
    public function rate(SizedBase $successes, SizedBase $difficulty, ?int $minSize = null): CheckRating;
}
