<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameCheckThrowOverride;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Бросает совместные проверки всех защитников манёвра.
 */
final class GameStrikeManeuverRoll
{
    /**
     * Создаёт бросок проверок.
     *
     * @param GameStrikeSheets $sheets Листы.
     *
     * @return void
     */
    public function __construct(private readonly GameStrikeSheets $sheets)
    {
    }

    /**
     * Бросает совместные проверки всех защитников.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $resolution Резолюция.
     * @param array<string, array<string, mixed>> $targets Защитники.
     * @param GameCheckRoll $checkRoll Бросок.
     *
     * @return void
     *
     * @throws GameInvalidException Если проверка чужая.
     */
    public function roll(GameRecord $game, array $resolution, array &$targets, GameCheckRoll $checkRoll): void
    {
        $attacker = $this->attacker($resolution);
        $attackerSheet = $this->sheets->getTargetSheet($game, $attacker['actor']);
        foreach ($targets as $key => $target) {
            $rolled = $this->one($game, $checkRoll, $attacker['check'], $attackerSheet, $target);
            if ($rolled !== null) {
                $targets[$key] = $rolled;
            }
        }
    }

    /**
     * Пара проверки атакующего и его актор.
     *
     * @param array<string, mixed> $resolution Резолюция.
     *
     * @return array{check: array<string, mixed>, actor: array<string, mixed>} Данные.
     *
     * @throws GameInvalidException Если проверка или актор битые.
     */
    private function attacker(array $resolution): array
    {
        $maneuver = is_array($resolution['maneuver'] ?? null) ? $resolution['maneuver'] : [];
        $checks = is_array($maneuver['attacker'] ?? null) ? $maneuver['attacker'] : [];
        $check = is_array($checks[0] ?? null) ? $checks[0] : null;
        $actor = is_array($resolution['attacker'] ?? null) ? $resolution['attacker'] : null;
        if ($check === null || $actor === null) {
            throw new GameInvalidException('Game maneuver check is invalid');
        }

        return ['check' => $check, 'actor' => $actor];
    }

    /**
     * Бросок одного защитника.
     *
     * @param GameRecord $game Игра.
     * @param GameCheckRoll $checkRoll Бросок.
     * @param array<string, mixed> $attackerCheck Пара атакующего.
     * @param array<string, mixed> $attackerSheet Лист атакующего.
     * @param array<string, mixed> $target Защитник.
     *
     * @return array<string, mixed>|null Защитник с броском.
     */
    private function one(
        GameRecord $game,
        GameCheckRoll $checkRoll,
        array $attackerCheck,
        array $attackerSheet,
        array $target,
    ): ?array {
        $check = is_array($target['check'] ?? null) ? $target['check'] : null;
        if ($check === null) {
            return null;
        }

        $rolls = $checkRoll->throwJointCheckSides(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            (string) $attackerCheck['check'],
            $attackerSheet,
            new GameCheckThrowOverride((string) $attackerCheck['characteristic']),
            (string) $check['check'],
            $this->sheets->getTargetSheet($game, $target['actor']),
            new GameCheckThrowOverride((string) $check['characteristic']),
        );
        $attackerSuccesses = (int) ($rolls['initiator']['roll']['base'] ?? 0);
        $targetSuccesses = (int) ($rolls['opponent']['roll']['base'] ?? 0);
        $won = $targetSuccesses > $attackerSuccesses;

        return ['roll' => $rolls['opponent'], 'won' => $won, 'decision' => $won ? null : 'keep'] + $target;
    }
}
