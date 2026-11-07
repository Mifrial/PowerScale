<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Чтение листа, раны и боя для сценария раны.
 */
final class GameWoundRows
{
    /**
     * Создаёт читатель.
     *
     * @param GameStrikeRules $rules Срез.
     * @param GameNpcRepository $npcs NPC игры.
     * @param GameBattleRepository $battles Бои.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Лист цели или актора.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид.
     * @param int $id Id.
     *
     * @return array{actualVersion: int, choices: array<string, mixed>, sheet: array<string, mixed>} Документ.
     *
     * @throws GameInvalidException Если лист NPC битый.
     * @throws GameNotFoundException Если строки нет.
     */
    public function document(GameRecord $game, string $kind, int $id): array
    {
        if ($kind === 'character') {
            $snapshot = $this->rules->data->characterSnapshot($id);

            return [
                'actualVersion' => $snapshot['actualVersion'],
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ];
        }

        $npc = $this->npcs->getById($id);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game npc was not found');
        }

        $version = $npc->getVersion();
        $choices = $version['choices'] ?? [];
        $sheet = $version['sheet'] ?? [];
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game npc sheet is invalid');
        }

        return [
            'actualVersion' => $npc->getActualVersion(),
            'choices' => $choices,
            'sheet' => $sheet,
        ];
    }

    /**
     * Строка состояния по id.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param int $stateId Id.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если строки нет.
     */
    public function rowOf(array $sheet, int $stateId): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            throw new GameInvalidException('Game wound was not found');
        }

        foreach ($states as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $stateId) {
                return $row;
            }
        }

        throw new GameInvalidException('Game wound was not found');
    }

    /**
     * Целые траты в карту ресурсов.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, int> Суммы.
     */
    public function spentAmounts(array $spends): array
    {
        $amounts = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                continue;
            }

            $amounts[$spend->getResourceCode()] = ($amounts[$spend->getResourceCode()] ?? 0) + $native;
        }

        return $amounts;
    }

    /**
     * Пара kind и id.
     *
     * @param mixed $pair Объект.
     *
     * @return array{kind: string, id: int} Пара.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function pair(mixed $pair): array
    {
        $kind = is_array($pair) ? ($pair['kind'] ?? null) : null;
        $id = is_array($pair) ? ($pair['id'] ?? null) : null;
        if (($kind !== 'character' && $kind !== 'npc') || !is_int($id) || $id < 1) {
            throw new GameInvalidException('Game wound actor is invalid');
        }

        return ['kind' => $kind, 'id' => $id];
    }

    /**
     * Открытый бой или null.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return GameBattleRecord|null Бой.
     *
     * @throws GameNotFoundException Если названного боя нет.
     * @throws GameBattleConflictException Если версия чужая.
     */
    public function battleOf(array $body): ?GameBattleRecord
    {
        $battleId = $body['battleId'] ?? 0;
        if (!is_int($battleId) || $battleId < 1) {
            return null;
        }

        $battle = $this->battles->find($battleId);
        $expected = $body['expectedBattleVersion'] ?? null;
        if ($battle === null) {
            throw new GameNotFoundException('Game battle was not found');
        }

        if (!is_int($expected) || $battle->getStateVersion() !== $expected) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }

        return $battle;
    }
}
