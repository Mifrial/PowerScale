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
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessStep;
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
        $step = $choice['processContext']['step'] ?? null;
        if (!$step instanceof ProcessStep) {
            $step = null;
        }

        if ($step !== null) {
            $spends = (new GameProcessRules())->replaceCosts($spends, $step);
        }

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
        $spends = $step === null
            ? (new GameActionFloor())->apply(
                $slice,
                $choice['actionRuleCode'],
                $adjusted['spends'],
                true,
            )
            : $adjusted['spends'];
        $debt = new GameActionDebt();
        $actorKey = $debt->key($kind, $id);
        $debt->assertFree($battle->getActionDebt(), $actorKey);
        $planned = $debt->plan($slice, $sheet, $spends, $debt->refillCode($slice), 'strike', $choice, $lane);
        $spends = $planned['spends'];
        $token = $this->tokenSpend($game, $choice, $sheet, $choices, $slice);
        if ($token !== null && !$planned['open']) {
            throw new GameInvalidException($subject . ' concentration requires an open strike');
        }

        if ($token !== null) {
            $spends[] = new ResourceSpend($token['resource'], new ScalarResourceValue($token['amount']));
        }

        $debtHold = null;
        if (!$planned['open']) {
            $debtHold = $battle->getActionDebt();
            $debtHold[$actorKey] = $planned['slot'];
        }

        $inventorySpend = $planned['open']
            && $this->rules->pushOf($game->getSpaceId(), $game->getRulesRevision(), $choice['actionRuleCode']) === null
            ? $this->rules->resolveInventorySpend(
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
            )
            : null;
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

        $cycle = $token === null
            ? null
            : (new GameConcentrationCycle())->markUsed($battle->getConcentrationCycle(), $actorKey);

        return [
            'pending' => $adjusted['pending'],
            'debtHold' => $debtHold,
            'cycle' => $cycle,
            'tokenSpend' => $token,
        ];
    }

    /**
     * Жетоны концентрации на удар.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{resource: string, amount: int, source: string, mode: string}|null Трата.
     *
     * @throws GameInvalidException Если жетон недоступен.
     */
    private function tokenSpend(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        CharacterRuleSlice $slice,
    ): ?array {
        $raw = $choice['tokenSpend'] ?? null;
        $amount = is_array($raw) ? ($raw['amount'] ?? 0) : $raw;
        if (!is_int($amount) || $amount < 1) {
            return null;
        }

        $concentration = new GameConcentration();
        $resource = $concentration->resourceCode($slice, $sheet);
        if ($resource === null) {
            throw new GameInvalidException('Game concentration resource is missing');
        }

        $checkCode = $this->rules->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $levels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
        if (!$concentration->allows($slice, $checkCode, null, $levels)) {
            throw new GameInvalidException('Game concentration is not allowed');
        }

        $concentration->assertAmount($slice, $sheet, $levels, $resource, $amount);
        $mode = is_array($raw) && is_string($raw['mode'] ?? null) ? $raw['mode'] : 'advantage';
        $card = $mode === 'accuracy'
            ? $this->accuracyCard($game, $choice, $sheet, $choices, $slice, $checkCode)
            : null;
        if ($mode === 'accuracy' && $card === null) {
            throw new GameInvalidException('Game concentration accuracy card is missing');
        }

        return [
            'resource' => $resource,
            'amount' => $amount,
            'source' => is_array($card) && is_string($card['source'] ?? null) ? $card['source'] : $resource,
            'mode' => $mode,
        ];
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
        $amount = $choice['spendConcentration'] ?? 0;
        if (!is_int($amount) || $amount < 1) {
            return null;
        }

        $slice = $this->rules->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $concentration = new GameConcentration();
        $resource = $concentration->resourceCode($slice, $sheet);
        if ($resource === null) {
            throw new GameInvalidException('Game concentration resource is missing');
        }

        $levels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
        if (!$concentration->allows($slice, $hitCode, null, $levels)) {
            throw new GameInvalidException('Game concentration is not allowed');
        }

        $concentration->assertAmount($slice, $sheet, $levels, $resource, $amount);

        return [
            'spend' => new ResourceSpend($resource, new ScalarResourceValue($amount)),
            'advantage' => new RollAdvantage($resource, $amount, $slice->findLive($resource)?->getName()),
            'cycle' => (new GameConcentrationCycle())->markUsed($cycle, $actorKey),
        ];
    }

    /**
     * Карточка с точностью жетона и проверка мастерства оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     *
     * @return array<string, mixed>|null Узел token_spend.
     *
     * @throws GameInvalidException Если мастерство ниже требования.
     */
    private function accuracyCard(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        CharacterRuleSlice $slice,
        string $checkCode,
    ): ?array {
        foreach ($slice->getLiveRules() as $rule) {
            $document = $rule->getSpecDocument();
            $node = $document['token_spend'] ?? null;
            if (!is_array($node) || ($node['check'] ?? null) !== $checkCode) {
                continue;
            }

            $bonus = is_array($node['bonus'] ?? null) ? $node['bonus'] : null;
            if ($bonus === null || ($bonus['target'] ?? null) !== 'accuracy') {
                continue;
            }

            $minimum = is_array($node['min_mastery'] ?? null) ? $node['min_mastery'] : null;
            if ($minimum !== null) {
                $mastery = $this->rules->weaponMasteryOf(
                    $game->getSpaceId(),
                    $game->getRulesRevision(),
                    $sheet,
                    $choices,
                    is_int($choice['itemInventoryId'] ?? null) ? $choice['itemInventoryId'] : 0,
                    is_string($choice['itemRuleCode'] ?? null) ? $choice['itemRuleCode'] : '',
                    is_string($choice['profileType'] ?? null) ? $choice['profileType'] : '',
                );
                if ($mastery->compare(new DimensionalNumber($minimum['base'] ?? 0, $minimum['size'] ?? 0)) < 0) {
                    throw new GameInvalidException('Game concentration accuracy mastery is insufficient');
                }
            }

            return $node;
        }

        return null;
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
