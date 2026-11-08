<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameStrikeDefenseTarget;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Смягчение, урон и операции листа цели.
 */
final class GameStrikeResolutionDamage
{
    private readonly GameStrikeAmounts $amounts;

    /**
     * Создаёт расчёт урона.
     *
     * @param GameStrikeRules $rules Срез удара.
     * @param GameStrikeSheets $sheets Листы удара.
     * @param GameStrikeResolutionContext $context Строка и участники.
     * @param GameStrikeResolutionActors $actors Снимки участников.
     * @param GameStrikeConsequences $consequences Последствия попадания.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeResolutionContext $context,
        private readonly GameStrikeResolutionActors $actors,
        private readonly GameStrikeConsequences $consequences,
    ) {
        $this->amounts = new GameStrikeAmounts();
    }

    /**
     * Смягчение выбранной реакции защитника по профилю удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param string $reaction Выбранная реакция защиты.
     * @param int $success Успех попадания.
     * @param array<string, mixed> $pending Карта ожидания атакующего.
     *
     * @return DimensionalNumber|null Пара или null, если у реакции нет dodge_soak.
     *
     * @throws GameInvalidException Если строка, карточка или закупка битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии или листа нет.
     */
    public function soakOf(
        GameRecord $game,
        array $open,
        string $reaction,
        int $success,
        array $pending = [],
    ): ?DimensionalNumber {
        $defender = $this->context->defenderOf($open);
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        $attackerKind = $open['attacker_kind'] ?? null;
        $attackerId = $open['attacker_id'] ?? null;
        if (!is_string($itemRuleCode) || !is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_string($attackerKind) || !is_int($attackerId)) {
            throw new GameInvalidException('Game strike attacker is invalid');
        }

        return $this->rules->defense->soak(
            $this->soakTarget($game, $defender, $itemRuleCode, $profileIndex, $reaction),
            is_string($open['action_rule_code'] ?? null) ? $open['action_rule_code'] : '',
            $success,
            $this->soakReduction($game, $attackerKind, $attackerId, $defender['profileType'], $pending),
        );
    }

    /**
     * Число формулы урона по строке открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param int|null $success Рейтинг успеха или null, если бонус от РУ не применяется.
     * @param int $hitNumber Номер попадания с единицы.
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int|null $extraCharacteristicModifier Дополнительный сдвиг формулы.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если строка или формула битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии нет.
     */
    public function damageOf(
        GameRecord $game,
        array $open,
        ?int $success = null,
        int $hitNumber = 1,
        int $strengthDrop = 0,
        ?int $extraCharacteristicModifier = null,
    ): DimensionalNumber {
        $row = $this->context->attackerOf($open);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $attackContext = $this->context->attackFilterContext($game, $row);
        $characteristicModifier = $this->context->attackCharacteristicModifier(
            $game,
            $row,
            $slice,
            $hitNumber,
            $success,
        );
        if ($extraCharacteristicModifier !== null) {
            $characteristicModifier += $extraCharacteristicModifier;
        }

        return $this->rules->damage->evaluate(
            $this->damageTarget($game, $row, $characteristicModifier, $strengthDrop, $attackContext),
        );
    }

    /**
     * Готовит операции листа и последствия попадания.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $open Строка удара.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success Успех.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>}
     *
     * @throws GameInvalidException Если строка, стойкость или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function prepareSheet(GameRecord $game, array $open, DimensionalNumber $injury, int $success): array
    {
        $defender = $this->context->defenderOf($open);
        $itemRuleCode = $open['item_rule_code'] ?? null;
        $profileIndex = $open['profile_index'] ?? null;
        if (!is_string($itemRuleCode)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        if (!is_int($profileIndex)) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return $this->sheetEffects(
            $game,
            $defender['kind'],
            $defender['id'],
            $injury,
            $success,
            $this->rules->outcome->damageTypeCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $itemRuleCode,
                $defender['profileType'],
                $profileIndex,
            ),
        );
    }

    /**
     * Урон по самому атакующему: тот же обработчик, другой тип урона и РУ.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника: character или npc.
     * @param int $id Идентификатор.
     * @param DimensionalNumber $injury Повреждение.
     * @param string $damageTypeCode Тип урона.
     * @param int $success РУ.
     * @param bool $ignoreDefense Игнорировать защиту и сопротивление.
     * @param bool $internal Внутренний урон: рана внутренняя, обычные слои не считаются.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>,
     *     actualVersion: int, injury: DimensionalNumber} Операции, последствия, версия и урон после слоёв.
     *
     * @throws GameInvalidException Если вид, стойкость или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    public function selfDamage(
        GameRecord $game,
        string $kind,
        int $id,
        DimensionalNumber $injury,
        string $damageTypeCode,
        int $success = 1,
        bool $ignoreDefense = true,
        bool $internal = false,
    ): array {
        $snapshot = $this->sheets->snapshot($game, $kind, $id);
        [$sheet, $choices] = $this->snapshotParts($snapshot);
        $applied = $this->selfInjury($game, $sheet, $choices, $injury, $damageTypeCode, $ignoreDefense, $internal);
        $prepared = $this->sheetEffects(
            $game,
            $kind,
            $id,
            $applied,
            $success,
            $damageTypeCode,
            $snapshot,
            $internal,
        );
        $prepared['injury'] = $applied;

        return $prepared;
    }

    /**
     * Урон по себе после собственных слоёв атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param DimensionalNumber $injury Повреждение.
     * @param string $damageTypeCode Тип урона.
     * @param bool $ignoreDefense Игнорировать защиту и сопротивление.
     * @param bool $internal Внутренний урон.
     *
     * @return DimensionalNumber Повреждение после слоёв.
     *
     * @throws GameInvalidException Если слой или тип битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если ревизии нет.
     */
    private function selfInjury(
        GameRecord $game,
        array $sheet,
        array $choices,
        DimensionalNumber $injury,
        string $damageTypeCode,
        bool $ignoreDefense,
        bool $internal,
    ): DimensionalNumber {
        if ($ignoreDefense && !$internal) {
            return $injury;
        }

        $resistance = $this->rules->defense->resistanceByType($game, $damageTypeCode, $sheet, $choices, $internal);

        return $this->amounts->injury($injury, $resistance, 1, null);
    }

    /**
     * Запрос урона с бонусом удержания.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, attackerId: int, itemRuleCode: string, profileType: string, profileIndex: int, inventoryId: int, actionRuleCode: string} $row Поля атакующего.
     * @param int $characteristicModifier Сдвиг характеристики.
     * @param int $strengthDrop Штраф силы броска.
     * @param array<string, mixed> $attackContext Контекст фильтра атаки.
     *
     * @return GameStrikeTarget Запрос.
     *
     * @throws GameInvalidException Если снимок NPC битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если NPC нет.
     */
    private function damageTarget(
        GameRecord $game,
        array $row,
        int $characteristicModifier,
        int $strengthDrop,
        array $attackContext,
    ): GameStrikeTarget {
        return $this->actors->target(
            $game,
            $row,
            $characteristicModifier,
            $strengthDrop,
            $this->rules->equipment->gripBonus($attackContext['occupyHands']),
        );
    }

    /**
     * Запрос защиты для смягчения.
     *
     * @param GameRecord $game Игра.
     * @param array{kind: string, id: int, profileType: string} $defender Защитник.
     * @param string $itemRuleCode Код предмета.
     * @param int $profileIndex Индекс профиля.
     * @param string $reaction Реакция.
     *
     * @return GameStrikeDefenseTarget Запрос.
     *
     * @throws GameInvalidException Если снимок NPC битый.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если NPC нет.
     */
    private function soakTarget(
        GameRecord $game,
        array $defender,
        string $itemRuleCode,
        int $profileIndex,
        string $reaction,
    ): GameStrikeDefenseTarget {
        $npcVersion = $defender['kind'] === 'npc'
            ? $this->sheets->getNpcVersion($game->getId(), $defender['id'])
            : null;

        return $this->actors->defenseTarget(
            $game,
            $defender['kind'],
            $defender['id'],
            $itemRuleCode,
            $defender['profileType'],
            $profileIndex,
            $reaction,
            null,
            0,
            null,
            0,
            $npcVersion,
        );
    }

    /**
     * Снижение смягчения от Реакции атакующего.
     *
     * @param GameRecord $game Игра.
     * @param string $attackerKind Вид атакующего.
     * @param int $attackerId Атакующий.
     * @param string $profileType Тип профиля.
     * @param array<string, mixed> $pending Карта ожидания.
     *
     * @return int Снижение.
     *
     * @throws GameInvalidException Если у атакующего нет Реакции.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function soakReduction(
        GameRecord $game,
        string $attackerKind,
        int $attackerId,
        string $profileType,
        array $pending,
    ): int {
        $npc = $attackerKind === 'npc' ? $this->actors->npcData($game->getId(), $attackerId) : null;

        return $this->rules->defense->dodgeSoakReduction(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $pending,
            $attackerKind,
            $attackerId,
            $profileType,
            1,
            $npc['sheet'] ?? null,
            $npc['choices'] ?? null,
            $npc['version'] ?? null,
        );
    }

    /**
     * Операции листа участника и версия, с которой их писать.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника: character или npc.
     * @param int $id Идентификатор.
     * @param DimensionalNumber $injury Повреждение.
     * @param int $success РУ.
     * @param string|null $damageTypeCode Тип урона.
     * @param array<string, mixed>|null $snapshot Готовый снимок листа.
     * @param bool $internal Внутренняя ли рана.
     *
     * @return array{operations: list<array<string, mixed>>, consequence: array<string, mixed>, actualVersion: int}
     *     Операции, последствия и версия.
     *
     * @throws GameInvalidException Если вид, стойкость или лист битые.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если листа нет.
     */
    private function sheetEffects(
        GameRecord $game,
        string $kind,
        int $id,
        DimensionalNumber $injury,
        int $success,
        ?string $damageTypeCode,
        ?array $snapshot = null,
        bool $internal = false,
    ): array {
        if ($kind !== 'character' && $kind !== 'npc') {
            throw new GameInvalidException('Game strike participant is invalid');
        }

        $snapshot ??= $this->sheets->snapshot($game, $kind, $id);
        [$sheet, $choices, $version] = $this->snapshotParts($snapshot);
        $effects = $this->consequences->effects(
            $game,
            $sheet,
            $injury,
            $success,
            $this->splitInjury($game, $kind, $id, $sheet, $choices, $version, $injury),
            $damageTypeCode,
            true,
            $this->endurance($game, $kind, $id, $sheet, $choices, $version),
            $internal,
        );

        return [
            'operations' => $effects['operations'],
            'consequence' => $effects['consequence'],
            'actualVersion' => $version,
        ];
    }

    /**
     * Части снимка листа: sheet, choices и версия.
     *
     * @param array<string, mixed> $snapshot Снимок.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: int}
     *
     * @throws GameInvalidException Если снимок битый.
     */
    private function snapshotParts(array $snapshot): array
    {
        $sheet = $snapshot['sheet']['sheet'] ?? null;
        $choices = $snapshot['sheet']['choices'] ?? null;
        $version = $snapshot['actualVersion'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($version)) {
            throw new GameInvalidException('Game strike sheet is invalid');
        }

        return [$sheet, $choices, $version];
    }

    /**
     * Деление обычного урона по листу цели.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Choices.
     * @param int $version Версия.
     * @param DimensionalNumber $injury Повреждение.
     *
     * @return array{kind: string, remainder: array{base: int, size: int}, quotient: int} Деление.
     *
     * @throws GameInvalidException Если формула битая.
     */
    private function splitInjury(
        GameRecord $game,
        string $kind,
        int $id,
        array $sheet,
        array $choices,
        int $version,
        DimensionalNumber $injury,
    ): array {
        return $this->rules->outcome->splitInjury(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $id,
            $kind === 'npc' ? $sheet : null,
            $injury,
            $kind === 'npc' ? $choices : null,
            $kind === 'npc' ? $version : null,
        );
    }

    /**
     * Стойкость участника из его листа.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид участника.
     * @param int $id Идентификатор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Choices.
     * @param int $version Версия.
     *
     * @return DimensionalNumber Стойкость.
     *
     * @throws GameInvalidException Если стойкость битая.
     */
    private function endurance(
        GameRecord $game,
        string $kind,
        int $id,
        array $sheet,
        array $choices,
        int $version,
    ): DimensionalNumber {
        return $this->rules->data->enduranceOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $id,
            $kind === 'npc' ? $sheet : null,
            $kind === 'npc' ? $choices : null,
            $kind === 'npc' ? $version : null,
        );
    }
}
