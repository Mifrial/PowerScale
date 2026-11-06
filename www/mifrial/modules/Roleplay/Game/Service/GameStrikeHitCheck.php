<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Состояние проверки попадания: код, auto-fail, переоценка и сознание.
 */
final class GameStrikeHitCheck
{
    /**
     * Принимает бросок и срезы.
     *
     * @param GameCheckRoll $hitRoll Бросок попадания.
     * @param GameStrikeSlices $slices Срезы ревизии.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $hitRoll,
        private readonly GameStrikeSlices $slices,
    ) {
    }

    /**
     * Код единственной живой проверки попадания.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     */
    public function findHitCode(int $spaceId, int $rulesRevision): string
    {
        return $this->hitRoll->findHitCode($spaceId, $rulesRevision);
    }

    /**
     * Проверяет auto-fail размерного броска попадания.
     *
     * @param array{base: int, size: int} $roll Размерный бросок.
     *
     * @return bool true, если достигнут минимум боевой проверки.
     */
    public function isHitAutoFail(array $roll): bool
    {
        return $this->hitRoll->isCombatAutoFail($roll);
    }

    /**
     * Переоценивает атаку против сырого пула уклона или готовой трудности.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     * @param array<string, mixed> $defenderRoll Бросок уклона или готовый {base,size}.
     *
     * @return array<string, mixed> Бросок атаки.
     *
     * @throws GameInvalidException Если трудность битая.
     */
    public function rerate(array $attackerRoll, array $defenderRoll): array
    {
        $difficulty = $defenderRoll;
        if (is_array($defenderRoll['roll'] ?? null) || is_array($defenderRoll['raw'] ?? null)) {
            $difficulty = is_array($defenderRoll['roll'] ?? null) ? $defenderRoll['roll'] : $defenderRoll['raw'];
        }

        if (!is_int($difficulty['base'] ?? null) || !is_int($difficulty['size'] ?? null)) {
            throw new GameInvalidException('Game strike defense roll is invalid');
        }

        return $this->hitRoll->rerate($attackerRoll, $difficulty);
    }

    /**
     * Проверяет, потерял ли персонаж сознание.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return bool true, если потерял.
     *
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function isUnconscious(int $spaceId, int $rulesRevision, array $sheet): bool
    {
        $slice = $this->slices->slice($spaceId, $rulesRevision);
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return false;
        }

        foreach ($states as $row) {
            if (!is_array($row) || !is_string($row['stateRuleCode'] ?? null)) {
                continue;
            }

            $rule = $slice->findLive($row['stateRuleCode']);
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $spec->isDeclineUnconscious()) {
                return true;
            }
        }

        return false;
    }
}
