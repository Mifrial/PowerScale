<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Списание стоимости объявления у authoritative атакующего.
 */
final class GameStrikeSpendAttacker
{
    /**
     * Создаёт списание объявления.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStrikeSpendCosts $costs Стоимость действия.
     * @param GameStrikeSpendDebt $debt Долг действия.
     * @param GameStrikeSpendToken $token Жетон атаки.
     * @param GameStrikeSpendInventory $inventory Инвентарь.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStrikeSpendCosts $costs,
        private readonly GameStrikeSpendDebt $debt,
        private readonly GameStrikeSpendToken $token,
        private readonly GameStrikeSpendInventory $inventory,
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
     * @return array{
     *     pending: array<string, mixed>,
     *     debtHold: array<string, mixed>|null,
     *     cycle: array<string, mixed>|null,
     *     tokenSpend: array{resource: string, amount: int, source: string, mode: string}|null
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
        [$kind, $id] = $this->actor($choice, $subject);
        $choices = $snapshot['sheet']['choices'];
        $sheet = $snapshot['sheet']['sheet'];
        $costs = $this->costs->resolve($game, $choice, $battle, $choices, $sheet, $kind, $id);
        $debt = $this->debt->plan($costs['slice'], $battle, $sheet, $costs['spends'], $choice, $lane, $kind, $id);
        $token = $this->token->spend($game, $choice, $sheet, $choices, $costs['slice']);
        if ($token !== null && $debt['planned']['open'] !== true) {
            throw new GameInvalidException($subject . ' concentration requires an open strike');
        }
        $hold = $this->debt->hold($battle, $debt['key'], $debt['planned']);
        $spends = $this->withToken($debt['planned']['spends'], $token);
        $inventory = $this->inventory->resolve($game, $choice, $choices, $kind, $id, $debt['planned']['open'] === true);
        $this->write($game, $sheet, $spends, $inventory, $kind, $id, $snapshot, $subject);

        return [
            'pending' => $costs['pending'],
            'debtHold' => $hold,
            'cycle' => $this->cycle($battle, $token, $debt['key']),
            'tokenSpend' => $token,
        ];
    }

    /**
     * Отметка использования жетона в цикле боя.
     *
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed>|null $token Жетон.
     * @param string $key Ключ атакующего.
     *
     * @return array<string, mixed>|null Цикл или null.
     */
    private function cycle(GameBattleRecord $battle, ?array $token, string $key): ?array
    {
        if ($token === null) {
            return null;
        }

        return (new GameConcentrationCycle())->markUsed($battle->getConcentrationCycle(), $key);
    }

    /**
     * Атакующий объявления.
     *
     * @param array<string, mixed> $choice Выбор атаки.
     * @param string $subject Подлежащее сообщений об ошибке.
     *
     * @return array{0: string, 1: int} Вид и id.
     *
     * @throws GameInvalidException Если атакующий невалиден.
     */
    private function actor(array $choice, string $subject): array
    {
        $kind = $choice['attacker']['type'] ?? null;
        $id = $choice['attacker']['id'] ?? null;
        if (!is_string($kind) || !is_int($id)) {
            throw new GameInvalidException($subject . ' attacker is invalid');
        }

        return [$kind, $id];
    }

    /**
     * Добавляет списание жетона к тратам.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     * @param array{resource: string, amount: int, source: string, mode: string}|null $token Жетон.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function withToken(array $spends, ?array $token): array
    {
        if ($token === null) {
            return $spends;
        }

        $spends[] = new ResourceSpend($token['resource'], new ScalarResourceValue($token['amount']));

        return $spends;
    }

    /**
     * Проверяет ресурсы и записывает траты в лист.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param array{kind: string, inventoryId: int, ruleCode: string}|null $inventory Инвентарь.
     * @param string $kind Вид атакующего.
     * @param int $id Атакующий.
     * @param array{actualVersion: int} $snapshot Снимок.
     * @param string $subject Подлежащее сообщений об ошибке.
     *
     * @return void
     *
     * @throws GameInvalidException Если ресурсов не хватает.
     */
    private function write(
        GameRecord $game,
        array $sheet,
        array $spends,
        ?array $inventory,
        string $kind,
        int $id,
        array $snapshot,
        string $subject,
    ): void {
        if ($spends !== [] && !$this->rules->spends->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $sheet,
            $spends,
        )) {
            throw new GameInvalidException($subject . ' attacker resource is insufficient');
        }

        if ($spends === [] && $inventory === null) {
            return;
        }

        $this->sheetWrites->write($game, $kind, $id, $snapshot['actualVersion'], [], null, null, $spends, $inventory);
    }
}
