<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Closure;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameCharacterRepository;
use Mifrial\Roleplay\Game\Repository\GameMemberRepository;
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
     * @param GameCharacterRepository $characters Участники Character.
     * @param GameMemberRepository $members Участники игры.
     * @param GameNpcRepository $npcs NPC.
     * @param GameStrikeSheetWrites $sheetWrites Запись деления.
     * @param ConflictSheetProjection $conflictProjection Проекция конфликтов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameBattleRepository $battles,
        private readonly GameCharacterRepository $characters,
        private readonly GameMemberRepository $members,
        private readonly GameNpcRepository $npcs,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly ConflictSheetProjection $conflictProjection,
    ) {
        $this->amounts = new GameStrikeAmounts();
    }

    /**
     * Записи целей. Отказ одной не выкидывает остальные.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param list<string|null> $refusals Non-CAS отказы по целям.
     * @param DimensionalNumber $damage Пара формулы атакующего.
     * @param int $rating Рейтинг попадания.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param Closure|null $penetrationFactory Lazy penetration resolver.
     *
     * @return list<array<string, mixed>> Итоги.
     *
     * @throws GameInvalidException Если строка цели или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    public function build(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
        DimensionalNumber $damage,
        array $attackerRoll,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $actorUserId,
        bool $viewAll,
        ?Closure $penetrationFactory = null,
    ): array {
        $results = [];
        $sharedPenetration = null;
        $penetrationEvaluated = false;
        $sharedPenetrationFactory = $penetrationFactory === null
            ? null
            : function () use (&$sharedPenetration, &$penetrationEvaluated, $penetrationFactory): DimensionalNumber {
                if (!$penetrationEvaluated) {
                    $sharedPenetration = $penetrationFactory();
                    $penetrationEvaluated = true;
                }
                if (!$sharedPenetration instanceof DimensionalNumber) {
                    throw new GameInvalidException('Game wide strike penetration is invalid');
                }

                return $sharedPenetration;
            };
        foreach ($rows as $index => $row) {
            $results[] = $this->one(
                $game,
                $row,
                $choices[$index],
                $refusals[$index] ?? null,
                $damage,
                $attackerRoll,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $actorUserId,
                $viewAll,
                $sharedPenetrationFactory,
            );
        }

        return $results;
    }

    /**
     * Stale CAS любой цели прерывает close до roll.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int}> $choices Защиты.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return list<string|null> Non-CAS коды отказа по целям.
     *
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameInvalidException Если строка цели битая.
     * @throws GameNotFoundException Если листа нет.
     */
    public function assertSheets(
        GameRecord $game,
        int $battleId,
        array $rows,
        array $choices,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $refusals = [];
        foreach ($rows as $index => $row) {
            $target = $this->defender($row);
            $refusals[$index] = $this->refusal($game, $battleId, $target, $choices[$index]);
            if ($refusals[$index] !== null) {
                continue;
            }

            $this->assertSheet(
                $game,
                $target,
                $choices[$index]['expectedSheetVersion'],
                $actorUserId,
                $viewAll,
            );
        }

        return $refusals;
    }

    /**
     * Разрешает коды block из authoritative inventory целей.
     *
     * @param GameRecord $game Игра.
     * @param list<array<string, mixed>> $rows Цели.
     * @param list<array<string, mixed>> $choices Защиты.
     * @param list<string|null> $refusals Отказы.
     *
     * @return list<array<string, mixed>> Authoritative защиты.
     *
     * @throws GameInvalidException Если документ цели или выбор битые.
     * @throws GameNotFoundException Если цель отсутствует.
     */
    public function resolveChoices(
        GameRecord $game,
        array $rows,
        array $choices,
        array $refusals,
    ): array {
        foreach ($rows as $index => $row) {
            if (($refusals[$index] ?? null) !== null || ($choices[$index]['reaction'] ?? null) !== 'block') {
                continue;
            }

            $target = $this->defender($row);
            $inventoryId = $choices[$index]['blockItemInventoryId'] ?? null;
            if (!is_int($inventoryId)) {
                throw new GameInvalidException('Game wide strike block item is invalid');
            }

            $choices[$index]['blockItemRuleCode'] = $this->rules->getBlockItemRuleCode(
                $this->targetChoices($game, $target),
                $inventoryId,
            );
        }

        return $choices;
    }

    /**
     * Одна цель.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $row Цель.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     * @param string|null $refusalCode Non-CAS код отказа.
     * @param DimensionalNumber $damage Пара принятой цели.
     * @param int $rating Рейтинг попадания.
     * @param string $itemRuleCode Предмет удара.
     * @param string $profileType Вид профиля.
     * @param int $profileIndex Смещение.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     * @param Closure|null $penetrationFactory Shared lazy penetration resolver.
     *
     * @return array<string, mixed> Запись.
     *
     * @throws GameInvalidException Если колонки или надетый код битые.
     * @throws GameNotFoundException Если ревизии или листа нет.
     */
    private function one(
        GameRecord $game,
        array $row,
        array $choice,
        ?string $refusalCode,
        DimensionalNumber $damage,
        array $attackerRoll,
        string $itemRuleCode,
        string $profileType,
        int $profileIndex,
        int $actorUserId,
        bool $viewAll,
        ?Closure $penetrationFactory,
    ): array {
        $target = $this->defender($row);
        if ($refusalCode !== null) {
            return $this->row($target, $refusalCode, null, null, null);
        }

        if ($choice['reaction'] === 'ignore') {
            $zero = $this->amounts->view($this->amounts->zero());

            return $this->row($target, null, null, $zero, $zero, null, $zero);
        }

        $spends = $this->resourceSpends($game, $target, $choice);
        if ($spends !== [] && !$this->rules->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->targetSheet($game, $target),
            $spends,
        )) {
            $zero = $this->amounts->view($this->amounts->zero());

            return $this->row($target, null, null, $zero, $zero, null, $zero);
        }

        $rating = $attackerRoll['rating'];
        $defenderRoll = null;
        if ($choice['reaction'] === 'block') {
            $defenderRoll = $this->rules->rollBlock(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision()),
                $this->targetSheet($game, $target),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $attackerRoll,
            );
        }
        $autoFail = $this->rules->isHitAutoFail($attackerRoll['roll']);
        $success = $autoFail || $rating <= 0
            ? 0
            : ($defenderRoll !== null && $defenderRoll['success'] ? 1 : $rating);
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $resistance = $success === 0
            ? $this->amounts->zero()
            : $this->resistanceOf(
                $game,
                $target,
                $itemRuleCode,
                $profileType,
                $profileIndex,
                $this->resistanceChoice($choice, $defenderRoll),
                $success,
                $penetrationFactory === null ? null : $penetrationFactory(),
            );
        $soak = $choice['reaction'] === 'dodge'
            ? $this->soakOf($game, $target, $itemRuleCode, $profileType, $profileIndex)
            : null;
        $injury = $this->amounts->injury($effectiveDamage, $resistance, $success, $soak);
        $record = $this->row(
            $target,
            null,
            $success,
            $this->amounts->view($effectiveDamage),
            $this->amounts->view($resistance),
            $soak === null ? null : $this->amounts->view($soak),
            $this->amounts->view($injury),
            $defenderRoll,
        );
        if (!$autoFail || $spends !== []) {
            $record['sheetVersion'] = $autoFail
                ? $this->sheetWrites->writeResources(
                    $game,
                    $target['type'],
                    $target['id'],
                    $choice['expectedSheetVersion'],
                    $spends,
                    ['type' => $target['type'], 'id' => $target['id']],
                    fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                        $game,
                        $target,
                        $exception,
                        $actorUserId,
                        $viewAll,
                    ),
                )
                : $this->sheetWrites->write(
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
                    ['type' => $target['type'], 'id' => $target['id']],
                    fn (CharacterConflictException|GameEconomyConflictException $exception): array => $this->projectWriteConflict(
                        $game,
                        $target,
                        $exception,
                        $actorUserId,
                        $viewAll,
                    ),
                    $spends,
                );
        }

        return $record;
    }

    /**
     * Убирает block layers после проигранного defender roll.
     *
     * @param array<string, mixed> $choice Реакция.
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
     *
     * @return array<string, mixed> Выбор для projection.
     */
    private function resistanceChoice(array $choice, ?array $defenderRoll): array
    {
        if ($choice['reaction'] === 'block'
            && ($defenderRoll === null || ($defenderRoll['success'] ?? false) !== true)
        ) {
            $choice['blockItemInventoryId'] = null;
        }

        return $choice;
    }

    /**
     * Разрешает resource spends accepted target.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Reaction.
     *
     * @return array<int, ResourceSpend> Native spends.
     *
     * @throws GameInvalidException If action or resource is invalid.
     * @throws GameNotFoundException If revision or target is absent.
     */
    private function resourceSpends(GameRecord $game, array $target, array $choice): array
    {
        if ($choice['reaction'] === 'ignore') {
            return [];
        }

        $blockRuleCode = $choice['reaction'] === 'block'
            ? $this->rules->getBlockItemRuleCode(
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
            )
            : ($choice['blockItemRuleCode'] ?? '');

        return $this->rules->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
            $choice['reaction'] === 'block' ? $choice['blockItemInventoryId'] : null,
            $blockRuleCode,
            $this->targetChoices($game, $target),
            $this->targetSheet($game, $target),
        );
    }

    /**
     * Возвращает authoritative sheet цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Sheet.
     *
     * @throws GameInvalidException If target document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function targetSheet(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['sheet'];
        }

        return $this->defenderSheet($game->getId(), $target['id']);
    }

    /**
     * Возвращает authoritative choices цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     *
     * @return array<string, mixed> Choices.
     *
     * @throws GameInvalidException If NPC document is invalid.
     * @throws GameNotFoundException If target is absent.
     */
    private function targetChoices(GameRecord $game, array $target): array
    {
        if ($target['type'] === 'character') {
            return $this->rules->characterSnapshot($target['id'])['choices'];
        }

        return $this->defenderChoices($game->getId(), $target['id']);
    }

    /**
     * Проецирует race-конфликт записи цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param CharacterConflictException|GameEconomyConflictException $exception Исходный конфликт.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectWriteConflict(
        GameRecord $game,
        array $target,
        CharacterConflictException|GameEconomyConflictException $exception,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            throw new GameInvalidException('Game wide strike conflict sheet is missing');
        }

        return $this->projectTargetSheet($game, $target, $current, $actorUserId, $viewAll);
    }

    /**
     * Код отказа или null.
     *
     * @param GameRecord $game Игра.
     * @param int $battleId Бой.
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null, expectedSheetVersion: int} $choice Защита.
     *
     * @return string|null Код.
     */
    private function refusal(GameRecord $game, int $battleId, array $target, array $choice): ?string
    {
        $code = null;
        try {
            $this->assertDefender($battleId, $target);
            $this->assertReaction($game, $target, $choice);
        } catch (GameNotFoundException) {
            $code = 'GAME_NOT_FOUND';
        } catch (GameInvalidException) {
            $code = 'GAME_INVALID';
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
     * @param int $rating Рейтинг попадания.
     * @param DimensionalNumber|null $penetration Проникновение.
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
        ?DimensionalNumber $penetration,
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
            $choice['blockItemInventoryId'] ?? null,
            $rating,
            $penetration,
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
     * @param array{type: string, id: int} $target Цель.
     * @param array{reaction: string, blockItemInventoryId: int|null, blockItemProfileIndex: int|null, blockItemRuleCode: string|null} $choice Защита.
     *
     * @return void
     *
     * @throws GameInvalidException Если реакция чужая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function assertReaction(GameRecord $game, array $target, array $choice): void
    {
        if (!in_array($choice['reaction'], ['ignore', 'dodge', 'block'], true)) {
            throw new GameInvalidException('Game wide strike reaction is invalid');
        }

        if ($choice['reaction'] === 'block'
            && (!is_int($choice['blockItemInventoryId'])
                || !is_int($choice['blockItemProfileIndex']))
        ) {
            throw new GameInvalidException('Game wide strike block item is invalid');
        }

        if ($choice['reaction'] === 'block') {
            $this->rules->assertBlockSelection(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $this->targetChoices($game, $target),
                $choice['blockItemInventoryId'],
                $choice['blockItemProfileIndex'],
                $choice['blockItemRuleCode'],
            );
        }
    }

    /**
     * Версия листа цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param int $expectedSheetVersion Ожидание.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function assertSheet(
        GameRecord $game,
        array $target,
        int $expectedSheetVersion,
        int $actorUserId,
        bool $viewAll,
    ): void {
        if ($target['type'] === 'character') {
            $snapshot = $this->rules->characterSnapshot($target['id']);
            $current = $snapshot['actualVersion'];
            $currentSheet = [
                'choices' => $snapshot['choices'],
                'sheet' => $snapshot['sheet'],
            ];
        } else {
            $npc = $this->npcs->getById($target['id']);
            if ($npc->getGameId() !== $game->getId()) {
                throw new GameNotFoundException('Game wide strike npc was not found');
            }

            $current = $npc->getActualVersion();
            $currentSheet = [
                'choices' => $npc->getVersion()['choices'] ?? [],
                'sheet' => $npc->getVersion()['sheet'] ?? [],
            ];
        }
        $sheet = $this->projectTargetSheet($game, $target, $currentSheet, $actorUserId, $viewAll);

        if ($current !== $expectedSheetVersion) {
            throw new GameBattleConflictException(
                $current,
                currentSheet: $sheet,
                target: ['type' => $target['type'], 'id' => $target['id']],
            );
        }
    }

    /**
     * Проецирует authoritative snapshot цели по правилам Game conflict.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $currentSheet Снимок листа.
     * @param int $actorUserId Актор.
     * @param bool $viewAll Полный просмотр.
     *
     * @return array{choices: array<string, mixed>, sheet: array<string, mixed>} Проекция.
     *
     * @throws GameInvalidException Если снимок листа битый.
     * @throws GameNotFoundException Если цель или membership отсутствуют.
     */
    private function projectTargetSheet(
        GameRecord $game,
        array $target,
        array $currentSheet,
        int $actorUserId,
        bool $viewAll,
    ): array {
        $choices = $currentSheet['choices'] ?? null;
        $sheet = $currentSheet['sheet'] ?? null;
        if (!is_array($choices) || !is_array($sheet)) {
            throw new GameInvalidException('Game wide strike sheet is invalid');
        }

        if ($target['type'] === 'character') {
            $membership = $this->characters->getByPair($game->getId(), $target['id']);
            $full = $viewAll
                || $game->getOwnerId() === $actorUserId
                || $membership->getCharacterOwnerId() === $actorUserId
                || $this->isGm($game->getId(), $actorUserId);

            return $this->conflictProjection->projectGameCharacter(
                $choices,
                $sheet,
                $membership->getSectionVisibility(),
                $full,
            );
        }

        $npc = $this->npcs->getById($target['id']);
        if ($npc->getGameId() !== $game->getId()) {
            throw new GameNotFoundException('Game wide strike npc was not found');
        }

        $full = $viewAll
            || $game->getOwnerId() === $actorUserId
            || $this->isGm($game->getId(), $actorUserId);
        $version = $this->conflictProjection->projectNpc(
            ['choices' => $choices, 'sheet' => $sheet],
            $npc->getVisibility(),
            $full,
        );

        return [
            'choices' => is_array($version['choices'] ?? null) ? $version['choices'] : [],
            'sheet' => is_array($version['sheet'] ?? null) ? $version['sheet'] : [],
        ];
    }

    /**
     * Проверяет роль GM.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Актор.
     *
     * @return bool true для GM.
     */
    private function isGm(int $gameId, int $actorUserId): bool
    {
        try {
            return $this->members->getByPair($gameId, $actorUserId)->getRole() === 'gm';
        } catch (GameNotFoundException) {
            return false;
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
     * @param array<string, mixed>|null $defenderRoll Roll защитника.
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
        ?array $defenderRoll = null,
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
        if ($defenderRoll !== null) {
            $record['defenderRoll'] = $defenderRoll;
        }
        if ($soak !== null) {
            $record['S'] = $soak;
        }

        return $record;
    }
}
