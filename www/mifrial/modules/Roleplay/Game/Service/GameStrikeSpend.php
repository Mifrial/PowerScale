<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Стоимость действия, списание и преобразование трат.
 */
final class GameStrikeSpend
{
    /**
     * Создаёт расчёт стоимости.
     *
     * @param GameStrikeRules $rules Срез и версия персонажа.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheetWrites $sheetWrites,
    ) {
    }

    /**
     * Списывает стоимость declaration у authoritative атакующего.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор атаки.
     * @param GameBattleRecord $battle Бой.
     * @param array{actualVersion: int, sheet: array{choices: array<string, mixed>, sheet: array<string, mixed>}} $snapshot
     *        Снимок атакующего.
     * @param string $lane Полоса долга.
     * @param string $subject Подлежащее сообщений об ошибке.
     *
     * @return array{pending: array<string, mixed>, debtHold: array<string, mixed>|null}
     *         Карта ожидания и удержанный долг.
     *
     * @throws GameInvalidException Если ресурс или атакующий невалидны.
     * @throws GameNotFoundException Если revision или NPC отсутствует.
     * @throws GameBattleConflictException Если CAS атакующего устарел.
     */
    public function spendAttacker(
        GameRecord $game,
        array $choice,
        GameBattleRecord $battle,
        array $snapshot,
        string $lane,
        string $subject,
    ): array {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException($subject . ' attacker is invalid');
        }

        $choices = $snapshot['sheet']['choices'];
        $sheet = $snapshot['sheet']['sheet'];
        $spends = $this->rules->resolveResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['actionRuleCode'],
            $choice['itemInventoryId'],
            $choice['itemRuleCode'],
            $choices,
            $sheet,
            $choice['chosenAmounts'],
        );
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $adjusted = (new GamePendingActionCosts())->adjustSpends(
            $slice,
            $choices,
            $choice['actionRuleCode'],
            $kind,
            $id,
            $battle->getPendingActionEffects(),
            $spends,
        );
        $spends = (new GameActionFloor())->apply(
            $slice,
            $choice['actionRuleCode'],
            $adjusted['spends'],
            true,
        );
        $debt = new GameActionDebt();
        $actorKey = $debt->key($kind, $id);
        $debt->assertFree($battle->getActionDebt(), $actorKey);
        $planned = $debt->plan($slice, $sheet, $spends, $debt->refillCode($slice), $choice, $lane);
        $spends = $planned['spends'];
        $debtHold = null;
        if (!$planned['open']) {
            $debtHold = $battle->getActionDebt();
            $debtHold[$actorKey] = $planned['slot'];
        }

        $inventorySpend = $planned['open'] ? $this->rules->resolveInventorySpend(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['itemRuleCode'],
            $choice['profileType'],
            $choice['profileIndex'],
            $choice['itemInventoryId'],
            $choice['ammunitionInventoryId'] ?? null,
            $kind,
            $id,
            $kind === 'npc' ? $choices : null,
        ) : null;
        if (
            $spends !== [] && !$this->rules->hasSufficientResources(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $sheet,
                $spends,
            )
        ) {
            throw new GameInvalidException($subject . ' attacker resource is insufficient');
        }

        if ($spends !== [] || $inventorySpend !== null) {
            $this->sheetWrites->write(
                $game,
                $kind,
                $id,
                $snapshot['actualVersion'],
                [],
                null,
                null,
                $spends,
                $inventorySpend,
            );
        }

        return ['pending' => $adjusted['pending'], 'debtHold' => $debtHold];
    }

    /**
     * Снимает долг атакующего после уплаты F1.
     *
     * @param GameBattleRecord $battle Бой.
     * @param string $kind Вид атакующего.
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> Новая карта долга.
     */
    public function releaseDebt(GameBattleRecord $battle, string $kind, int $id): array
    {
        $debt = $battle->getActionDebt();
        unset($debt[(new GameActionDebt())->key($kind, $id)]);

        return $debt;
    }

    /**
     * Разрешает траты принятой реакции цели.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Реакция.
     * @param array<string, mixed> $choices Авторитетные выборы цели.
     * @param array<string, mixed> $sheet Авторитетный лист цели.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если действие или ресурс невалидны.
     * @throws GameNotFoundException Если ревизии или цели нет.
     */
    public function getReactionSpends(
        GameRecord $game,
        array $choice,
        array $choices,
        array $sheet,
    ): array {
        if ($choice['reaction'] === 'ignore') {
            return [];
        }

        $blockRuleCode = $choice['reaction'] === 'block'
            ? $this->rules->getBlockItemRuleCode($choices, $choice['blockItemInventoryId'])
            : ($choice['blockItemRuleCode'] ?? '');

        return $this->rules->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $choice['reaction'],
            $choice['reaction'] === 'block' ? $choice['blockItemInventoryId'] : null,
            $blockRuleCode,
            $choices,
            $sheet,
        );
    }

    /**
     * Базовая стоимость действия в ОД.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Карточка.
     * @param bool $strike Удар ли это.
     *
     * @return int Стоимость.
     */
    public function getActionAp(CharacterRuleSlice $slice, string $ruleCode, bool $strike = false): int
    {
        if ($ruleCode === '') {
            return 0;
        }

        $spec = $slice->findLive($ruleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return 0;
        }

        $turnCode = $this->getTurnResourceCode($slice);
        $sum = 0;
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if ($turnCode !== null && $component->getResourceCode() !== $turnCode) {
                continue;
            }

            $amount = $component->getAmount();
            if (is_int($amount)) {
                $sum += $amount;
            } elseif ($amount instanceof DimensionalNumber) {
                $sum += $amount->getBase();
            }
        }

        return $strike ? max($sum, (new GameActionFloor())->floorOf($slice, $ruleCode, true)) : $sum;
    }

    /**
     * Код ресурса turn refill.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string|null Код или null.
     */
    public function getTurnResourceCode(CharacterRuleSlice $slice): ?string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                return $rule->getCode();
            }
        }

        return null;
    }

    /**
     * Целые траты в карту ресурсов.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<string, int> Суммы.
     */
    public function getSpendAmounts(array $spends): array
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
     * Карта ресурсов в траты.
     *
     * @param array<string, int> $amounts Суммы.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    public function getSpendRows(array $amounts): array
    {
        $rows = [];
        foreach ($amounts as $code => $amount) {
            $rows[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return $rows;
    }
}
