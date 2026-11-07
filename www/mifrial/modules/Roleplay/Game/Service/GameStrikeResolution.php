<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Расчёт бросков, урона и последствий удара. Без записи.
 */
final class GameStrikeResolution
{
    public GameStrikeResolutionRolls $rolls;

    public GameStrikeResolutionDefense $defense;

    public GameStrikeResolutionDamage $damage;

    /**
     * Создаёт расчёт.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param GameStrikeSheets $sheets Листы и конфликты.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeConsequences $consequences Последствия попадания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        GameStrikeSheets $sheets,
        GameNpcRepository $npcs,
        GameStrikeConsequences $consequences,
    ) {
        $context = new GameStrikeResolutionContext($rules, $sheets, $npcs);
        $actors = new GameStrikeResolutionActors($npcs);
        $this->rolls = new GameStrikeResolutionRolls($rules, $sheets, $context, $actors);
        $this->defense = new GameStrikeResolutionDefense($rules, $context, $actors);
        $this->damage = new GameStrikeResolutionDamage($rules, $sheets, $context, $actors, $consequences);
    }

    /**
     * Число успеха по броску атакующего и ответу защитника.
     *
     * @param array<string, mixed> $attackerRoll Roll атаки.
     * @param array<string, mixed>|null $defenderRoll Roll защиты.
     * @param string $reaction Реакция.
     *
     * @return int Успех.
     *
     * @throws GameInvalidException Если рейтинг атаки невалиден.
     */
    public function successOf(array $attackerRoll, ?array $defenderRoll, string $reaction): int
    {
        $rating = $attackerRoll['rating'] ?? null;
        if (!is_int($rating)) {
            throw new GameInvalidException('Game strike attacker roll is invalid');
        }

        $autoFail = $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll']);
        $defenderWon = $defenderRoll !== null && ($defenderRoll['success'] ?? false) === true;

        return $reaction === 'dodge'
            ? (($autoFail || $defenderWon) ? 0 : max(0, $rating))
            : ($autoFail || $rating <= 0 ? 0 : ($defenderWon ? 1 : $rating));
    }

    /**
     * Успех дальнего боя после размерной совместной проверки.
     *
     * @param array<string, mixed> $attackerRoll Бросок атаки.
     *
     * @return int Успех.
     *
     * @throws GameInvalidException Если рейтинг битый.
     */
    public function successOfRanged(array $attackerRoll): int
    {
        $rating = $attackerRoll['rating'] ?? null;
        if (!is_int($rating)) {
            throw new GameInvalidException('Game strike attacker roll is invalid');
        }

        return $this->rules->hitCheck->isHitAutoFail($attackerRoll['roll']) ? 0 : max(0, $rating);
    }
}
