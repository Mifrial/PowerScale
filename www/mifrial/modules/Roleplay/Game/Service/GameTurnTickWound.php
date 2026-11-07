<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IBloodClotting;

/**
 * Тик раны: бросок свёртывания и остаток кровотечения.
 */
final class GameTurnTickWound
{
    /**
     * Создаёт тик раны.
     *
     * @param GameCheckRoll $checks Бросок.
     * @param GameMechanicResolver $resolver Резолв механик.
     * @param GameTurnSheetStates $states Строки листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCheckRoll $checks,
        private readonly GameMechanicResolver $resolver,
        private readonly GameTurnSheetStates $states,
    ) {
    }

    /**
     * Свёртывание раны и её вклад в кровь.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $row Строка раны.
     *
     * @return array{tick: int, operations: list<array<string, mixed>>, roll: array<string, mixed>|null} Итог.
     *
     * @throws GameInvalidException Если маркер свёртывания битый.
     */
    public function tick(GameRecord $game, CharacterRuleSlice $slice, array $sheet, array $row): array
    {
        $strength = $this->states->strength($row);
        $before = is_int($row['clotting'] ?? null) ? $row['clotting'] : 0;
        $bandage = is_int($row['bandage'] ?? null) ? $row['bandage'] : 0;
        $shot = $this->clotting($game, $slice, $sheet, $row, $strength, $before);
        $operations = [];
        if ($shot['clotting'] !== $before) {
            $operations[] = ['kind' => 'patchState', 'id' => $row['id'], 'clotting' => $shot['clotting']];
        }

        $closure = min($strength, $bandage + $shot['clotting']);
        if (is_array($row['heldBy'] ?? null)) {
            $closure = max($closure, 1);
        }

        return [
            'tick' => max(0, $strength - min($strength, $closure)),
            'operations' => $operations,
            'roll' => $shot['roll'],
        ];
    }

    /**
     * Бросок свёртывания и новое значение.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $row Строка раны.
     * @param int $strength Сила раны.
     * @param int $clotting Текущее свёртывание.
     *
     * @return array{clotting: int, roll: array<string, mixed>|null} Свёртывание и бросок.
     *
     * @throws GameInvalidException Если маркер свёртывания битый.
     */
    private function clotting(
        GameRecord $game,
        CharacterRuleSlice $slice,
        array $sheet,
        array $row,
        int $strength,
        int $clotting,
    ): array {
        $roll = null;
        $marker = $this->marker($slice, $row['stateRuleCode']);
        if ($marker !== null) {
            $thrown = $this->checks->throwWithDifficulty(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $marker,
                $sheet,
                ['base' => 0, 'size' => 0],
            );
            $roll = $thrown;
            if ($thrown['success'] && $thrown['rating'] > 0) {
                $clotting = min($strength, $clotting + $thrown['rating']);
            }
        }

        return ['clotting' => $clotting, 'roll' => $roll];
    }

    /**
     * Код маркера строки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $code Код.
     *
     * @return string|null Код маркера или null.
     *
     * @throws GameInvalidException Если проверка маркера битая.
     */
    private function marker(CharacterRuleSlice $slice, mixed $code): ?string
    {
        $rule = is_string($code) ? $slice->findLive($code) : null;
        if (!$rule instanceof CharacterResolvedRule) {
            return null;
        }

        foreach ($rule->getMechanics() as $binding) {
            $check = $this->checkOf($code, $binding);
            if ($check !== null) {
                return $check;
            }
        }

        return null;
    }

    /**
     * Проверка свёртывания из одной механики.
     *
     * @param string $code Код карточки.
     * @param mixed $binding Механика строки.
     *
     * @return string|null Код проверки или null.
     *
     * @throws GameInvalidException Если проверка объявлена без кода.
     */
    private function checkOf(string $code, mixed $binding): ?string
    {
        if (!is_array($binding) || !is_int($binding['mechanic_id'] ?? null)) {
            return null;
        }

        $handler = $this->resolver->handler($code, $binding['mechanic_id'], 'Game turn clotting check is invalid');
        if (!$handler instanceof IBloodClotting) {
            return null;
        }

        $payload = $binding['mechanic_payload'] ?? [];
        $check = is_array($payload) ? ($payload['check_code'] ?? null) : null;
        if (!is_string($check) || $check === '') {
            throw new GameInvalidException('Game turn clotting check is invalid');
        }

        return $check;
    }
}
