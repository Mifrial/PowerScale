<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;

/**
 * Цены карточки раны, срез и пол 2 у затронутого ресурса.
 */
final class GameWoundPrice
{
    /**
     * Создаёт расчёт цены.
     *
     * @param GamePendingActionCosts $pendingCosts Карта ожидания.
     * @param GameWoundGrants $grants Гранты навыков.
     * @param GameWoundRows $rows Чтение боя.
     *
     * @return void
     */
    public function __construct(
        private readonly GamePendingActionCosts $pendingCosts,
        private readonly GameWoundGrants $grants,
        private readonly GameWoundRows $rows,
    ) {
    }

    /**
     * Цены карточки, ожидание и пол 2 у затронутого ресурса.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка.
     * @param array{kind: string, id: int} $actor Актор.
     * @param array<string, mixed> $body Тело.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>} Трата и карта.
     *
     * @throws GameInvalidException Если у карточки нет ресурса.
     * @throws GameNotFoundException Если боя нет.
     * @throws GameBattleConflictException Если версия боя чужая.
     */
    public function of(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        array $actor,
        array $body,
    ): array {
        $amounts = $this->cardAmounts($slice, $actionRuleCode);
        $touched = $this->grants->cuts($slice, $choices, $actionRuleCode, $amounts);
        $declared = $this->declare($slice, $choices, $actionRuleCode, $actor, $this->rows->battleOf($body), $amounts);

        return [
            'spends' => $this->spends($declared['amounts'], array_merge($touched, $declared['touched'])),
            'pending' => $declared['pending'],
        ];
    }

    /**
     * Применяет cost-эффекты боя к ценам.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка.
     * @param array{kind: string, id: int} $actor Актор.
     * @param GameBattleRecord|null $battle Бой или null.
     * @param array<string, int> $amounts Цены.
     *
     * @return array{amounts: array<string, int>, pending: array<string, mixed>, touched: array<string, bool>} Итог.
     */
    private function declare(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        array $actor,
        ?GameBattleRecord $battle,
        array $amounts,
    ): array {
        if ($battle === null) {
            return ['amounts' => $amounts, 'pending' => [], 'touched' => []];
        }

        return $this->pendingCosts->declare(
            $slice,
            $choices,
            $actionRuleCode,
            $actor['kind'],
            $actor['id'],
            $battle->getPendingActionEffects(),
            $amounts,
            false,
        );
    }

    /**
     * Траты с полом 2 у затронутого ресурса.
     *
     * @param array<string, int> $amounts Цены.
     * @param array<string, bool> $touched Затронутые ресурсы.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function spends(array $amounts, array $touched): array
    {
        $spends = [];
        foreach ($amounts as $code => $amount) {
            $value = array_key_exists($code, $touched) ? max($amount, 2) : $amount;
            $spends[] = new ResourceSpend($code, new ScalarResourceValue($value));
        }

        return $spends;
    }

    /**
     * Целые цены всех resource-компонентов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return array<string, int> Суммы.
     *
     * @throws GameInvalidException Если компонента нет или цена не целая.
     */
    private function cardAmounts(CharacterRuleSlice $slice, string $actionRuleCode): array
    {
        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            throw new GameInvalidException('Game wound action card is invalid');
        }

        $amounts = [];
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if (!is_int($component->getAmount())) {
                throw new GameInvalidException('Game wound action resource is invalid');
            }

            $code = $component->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $component->getAmount();
        }

        if ($amounts === []) {
            throw new GameInvalidException('Game wound action has no resource');
        }

        return $amounts;
    }
}
