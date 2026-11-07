<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Правка строки раны по операции: зажим, отпускание и перевязка.
 */
final class GameWoundPatch
{
    /**
     * Создаёт правку.
     *
     * @param GameWoundHands $hands Свободные руки.
     * @param GameWoundGrants $grants Гранты навыков.
     *
     * @return void
     */
    public function __construct(
        private readonly GameWoundHands $hands,
        private readonly GameWoundGrants $grants,
    ) {
    }

    /**
     * Правка строки по операции.
     *
     * @param string $operation hold, release или bandage.
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист актора.
     * @param array<string, mixed> $row Строка раны.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return array<string, mixed> patchState.
     *
     * @throws GameInvalidException Если операция отвергнута.
     */
    public function of(
        string $operation,
        CharacterRuleSlice $slice,
        array $actorSheet,
        array $row,
        array $actor,
        GameRecord $game,
    ): array {
        if (($row['internal'] ?? false) === true && $operation !== 'release') {
            throw new GameInvalidException('Game wound is internal');
        }

        if ($operation === 'hold') {
            return $this->hold($slice, $actorSheet, $row, $actor, $game);
        }

        if ($operation === 'release') {
            return ['kind' => 'patchState', 'id' => $row['id'], 'heldBy' => null];
        }

        if ($operation === 'bandage') {
            return $this->bandage($slice, $actorSheet['choices'], $row);
        }

        throw new GameInvalidException('Game wound action is invalid');
    }

    /**
     * Зажим, если рука свободна и строка ещё не зажата.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array{choices: array<string, mixed>, sheet: array<string, mixed>} $actorSheet Лист актора.
     * @param array<string, mixed> $row Строка.
     * @param array{kind: string, id: int} $actor Держатель.
     * @param GameRecord $game Игра.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если руки нет или зажим уже стоит.
     */
    private function hold(
        CharacterRuleSlice $slice,
        array $actorSheet,
        array $row,
        array $actor,
        GameRecord $game,
    ): array {
        if (($row['heldBy'] ?? null) !== null) {
            throw new GameInvalidException('Game wound is already held');
        }

        if ($this->hands->freeHands($slice, $actorSheet, $actor, $game) < 1) {
            throw new GameInvalidException('Game wound hold has no free hand');
        }

        return ['kind' => 'patchState', 'id' => $row['id'], 'heldBy' => $actor];
    }

    /**
     * Перевязка: плюс 2, не выше силы и потолка 4, пока нет гранта помощи.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если сила не число.
     */
    private function bandage(CharacterRuleSlice $slice, array $choices, array $row): array
    {
        $strength = $row['value'] ?? null;
        $bandage = $row['bandage'] ?? 0;
        if (!is_int($strength) || !is_int($bandage)) {
            throw new GameInvalidException('Game wound strength is invalid');
        }

        $aided = $this->grants->hasAid($slice, $choices) || ($row['aided'] ?? false) === true;
        $next = $bandage + 2;
        if (!$aided) {
            $next = min($next, 4);
        }

        return [
            'kind' => 'patchState',
            'id' => $row['id'],
            'bandage' => min($next, $strength),
            'aided' => $aided,
        ];
    }
}
