<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Запись цели широкого удара. Число принятой цели приходит снаружи.
 */
final class GameWideStrikeResults
{
    private readonly GameStrikeAmounts $amounts;

    /**
     * Создаёт расчёт отказа.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param GameBattleRepository $battles Состав.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeSheetWrites $sheetWrites Запись деления.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
        $this->amounts = new GameStrikeAmounts();
    }

    /**
     * Записи целей. Отказ одной не выкидывает остальные.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param DimensionalNumber $damage Пара формулы атакующего.
     * @param int $rating Рейтинг попадания.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     *
     * @return list<array<string, mixed>> Итоги.
     *
     * @throws GameInvalidException Если строка цели или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function build(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        DimensionalNumber $damage,
        int $rating,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): array {
        $results = [];
        foreach ($rows as $index => $row) {
            $results[] = $this->one(
                $game,
                $battleId,
                $row,
                $choices[$index],
                $damage,
                $rating,
                $itemRuleCode,
                $profileType,
                $profileIndex,
            );
        }

        return $results;
    }

    /**
     * Одна цель.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array<string, mixed> $row Цель.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param DimensionalNumber $damage Пара принятой цели.
     * @param int $rating Рейтинг попадания.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws GameInvalidException Если колонки или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function one(
        GameRecord $game,
        int $battleId,
        array $row,
        array $choice,
        DimensionalNumber $damage,
        int $rating,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): array {
        $target = $this->defender($row);
        $code = $this->refusal($game, $battleId, $target, $choice);
        if ($code !== null) {
            return $this->row($target, $code, null, null, null);
        }

        $resistance = $this->resistanceOf(
            $game,
            $target,
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $choice,
            $rating,
        );
        $soak = $choice['reaction'] === 'dodge'
            ? $this->soakOf($game, $target, $itemRuleCode, $profileType, $profileIndex)
            : null;
        $injury = $this->amounts->injury($damage, $resistance, $rating, $soak);
        $record = $this->row(
            $target,
            null,
            $rating,
            $this->amounts->view($damage),
            $this->amounts->view($resistance),
            $soak === null ? null : $this->amounts->view($soak),
            $this->amounts->view($injury),
        );
        $record['sheetVersion'] = $this->sheetWrites->write(
            $game,
            $target['type'],
            $target['id'],
            $choice['expectedSheetVersion'],
            $this->rules->splitInjury(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $target['type'],
                $target['id'],
                $target['type'] === 'npc' ? $this->defenderSheet($game->getId(), $target['id']) : null,
                $injury,
            ),
        );

        return $record;
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->assertDefender($battleId, $target);
            $this->assertReaction($game, $choice);
            $this->assertSheet($game->getId(), $target, $choice['expectedSheetVersion']);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
        } catch (GameBattleConflictException) {
            $code = 'GAME_CONFLICT';
        }

        return $code;
    }

    /**
     * Сопротивление брони цели. Вызов снаружи refusal: отказ среза не становится code цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param int $rating Рейтинг.
     *
     * @return DimensionalNumber Сумма слоёв или ноль.
     *
     * @throws GameInvalidException Если профиль или лист битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function resistanceOf(
        GameRecord $game,
        array $target,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        array $choice,
        int $rating,
    ): DimensionalNumber {
        return $this->rules->evaluateResistance(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $this->defenderSheet($game->getId(), $target['id']) : null,
            $target['type'] === 'npc' ? $this->defenderChoices($game->getId(), $target['id']) : null,
            $choice['reaction'],
            $choice['blockItemRuleCode'] ?? null,
            $rating,
        );
    }

    /**
     * Снимок sheet NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ sheet.
     *
     * @throws GameInvalidException Если строка чужая или снимка нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function defenderSheet(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $sheet = $npc->getGameId() === $gameId ? ($npc->getVersion()['sheet'] ?? null) : null;
        if (!is_array($sheet)) {
            throw new GameInvalidException('Game wide strike npc sheet is missing');
        }

        return $sheet;
    }

    /**
     * Документ choices NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     * @throws GameNotFoundException Если строки нет.
     */
    private function defenderChoices(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }

    /**
     * Смягчение уклонения цели. Вызов снаружи refusal.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если карточка или закупка битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function soakOf(
        GameRecord $game,
        array $target,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
    ): DimensionalNumber {
        return $this->rules->evaluateSoak(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $itemRuleCode,
            $profileType,
            $profileIndex,
            $target['type'],
            $target['id'],
            $target['type'] === 'npc' ? $this->defenderSheet($game->getId(), $target['id']) : null,
        );
    }

    /**
     * Защитник всё ещё в составе.
     *
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return void
     *
     * @throws GameNotFoundException Если пары нет.
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertDefender(int $battleId, array $target): void
    {
        $this->assertKind($target['type']);
        if (!in_array($target, $this->battles->findParticipants($battleId), true)) {
            throw new GameNotFoundException('Game battle participant was not found');
        }
    }

    /**
     * Реакция и предмет блока этой цели.
     *
     * @param GameRecord $game Игра.
     * @param array{reaction: string, blockItemRuleCode: string|null} $choice Защита.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция чужая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertReaction(GameRecord $game, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game wide strike reaction is invalid');
        }

        if ($choice['reaction'] === 'block' && $choice['blockItemRuleCode'] === null) {
            throw new GameInvalidException('Game wide strike block item is invalid');
        }

        if ($choice['blockItemRuleCode'] !== null) {
            $this->rules->assertBlock($game->getSpaceId(), $game->getRulesRevision(), $choice['blockItemRuleCode']);
        }
    }

    /**
     * Версия листа цели.
     *
     * @param int $gameId Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $expectedSheetVersion Ожидание.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function assertSheet(int $gameId, array $target, int $expectedSheetVersion): void
    {
        $current = $target['type'] === 'character'
            ? $this->rules->characterVersion($target['id'])
            : $this->npcVersion($gameId, $target['id']);
        if ($current !== $expectedSheetVersion) {
            throw new GameBattleConflictException($current);
        }
    }

    /**
     * Пара защитника из строки.
     *
     * @param array<string, mixed> $row Цель.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если колонки битые.
     */
    private function defender(array $row): array
    {
        $kind = $row['defender_kind'] ?? null;
        $defenderId = $row['defender_id'] ?? null;
        if (!is_string($kind) || !is_int($defenderId)) {
            throw new GameInvalidException('Game wide strike target row is invalid');
        }

        return ['type' => $kind, 'id' => $defenderId];
    }

    /**
     * Версия NPC этой игры.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return int Счётчик.
     *
     * @throws GameNotFoundException Если строки нет или она чужая.
     */
    private function npcVersion(int $gameId, int $npcId): int
    {
        $npc = $this->npcs->getById($npcId);
        if ($npc->getGameId() !== $gameId) {
            throw new GameNotFoundException('Game wide strike npc was not found');
        }

        return $npc->getActualVersion();
    }

    /**
     * Вид участника.
     *
     * @param string $kind Вид.
     *
     * @return void
     *
     * @throws GameInvalidException Если вид чужой.
     */
    private function assertKind(string $kind): void
    {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game wide strike participant is invalid');
        }
    }

    /**
     * Запись цели.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param string|null $code Код отказа или null.
     * @param int|null $rating Рейтинг принятой цели или null.
     * @param array{base: int, size: int}|null $damage Пара принятой цели или null.
     * @param array{base: int, size: int}|null $resistance Сопротивление цели или null.
     * @param array{base: int, size: int}|null $soak Смягчение dodge или null, если ключ не пишется.
     * @param array{base: int, size: int}|null $injury Повреждение принятой цели или null.
     *
     * @return array<string, mixed> Запись.
     */
    private function row(
        array $target,
        ?string $code,
        ?int $rating,
        ?array $damage,
        ?array $resistance,
        ?array $soak = null,
        ?array $injury = null,
    ): array {
        $record = [
            'target' => $target,
            'success' => $rating,
            'damage' => $damage,
            'resistance' => $resistance,
            'injury' => $injury,
            'sheetVersion' => null,
            'code' => $code,
        ];
        if ($soak !== null) {
            $record['S'] = $soak;
        }

        return $record;
    }
}
