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
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Стоимость действия, списание и преобразование трат.
 */
final class GameStrikeSpend
{
    private readonly GameStrikeSpendAttacker $attacker;

    private readonly GameStrikeSpendDefender $defender;

    private readonly GameStrikeSpendActionAp $actionAp;

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
        GameStrikeSheetWrites $sheetWrites,
    ) {
        $concentration = new GameStrikeSpendConcentration();
        $this->attacker = new GameStrikeSpendAttacker(
            $rules,
            $sheetWrites,
            new GameStrikeSpendCosts($rules),
            new GameStrikeSpendDebt(),
            new GameStrikeSpendToken($rules, $concentration),
            new GameStrikeSpendInventory($rules),
        );
        $this->defender = new GameStrikeSpendDefender($rules, $concentration);
        $this->actionAp = new GameStrikeSpendActionAp();
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
     * @return array{
     *     pending: array<string, mixed>,
     *     debtHold: array<string, mixed>|null,
     *     cycle: array<string, mixed>|null,
     *     tokenSpend: array{resource: string, amount: int, source: string, mode: string}|null,
     *     spent: int
     * } Карта ожидания и удержанный долг.
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
        return $this->attacker->spendAttacker($game, $choice, $battle, $snapshot, $lane, $subject);
    }

    /**
     * Жетон концентрации защиты удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $sheet Лист цели.
     * @param string $hitCode Карточка проверки попадания.
     * @param string $actorKey Ключ цели.
     * @param array<string, mixed> $cycle Цикл боя.
     *
     * @return array{spend: ResourceSpend, advantage: RollAdvantage, cycle: array<string, mixed>}|null Трата.
     *
     * @throws GameInvalidException Если жетон недоступен.
     */
    public function defenderToken(
        GameRecord $game,
        array $choice,
        array $sheet,
        string $hitCode,
        string $actorKey,
        array $cycle,
    ): ?array {
        return $this->defender->defenderToken($game, $choice, $sheet, $hitCode, $actorKey, $cycle);
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
            ? $this->rules->equipment->getBlockItemRuleCode($choices, $choice['blockItemInventoryId'])
            : ($choice['blockItemRuleCode'] ?? '');

        return $this->rules->spends->resolveReactionResourceSpends(
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
        return $this->actionAp->get($slice, $ruleCode, $strike);
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
        return (new GameActionFloor())->turnResourceCode($slice);
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
