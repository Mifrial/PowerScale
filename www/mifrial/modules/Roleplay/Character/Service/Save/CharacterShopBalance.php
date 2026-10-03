<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Save;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MoneyGrant;

/**
 * Остаток шопа на create: бюджет + гранты − cost_gm купленного не-innate инвентаря.
 */
final class CharacterShopBalance
{
    /**
     * Создаёт шаг.
     *
     * @param CharacterSpecReader $specReader Цена и врождённость предмета.
     * @param CharacterDonorGrants $donorGrants Денежные гранты.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterSpecReader $specReader,
        private readonly CharacterDonorGrants $donorGrants,
    ) {
    }

    /**
     * Считает наличные и отказ перерасхода.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, CharacterAbilityChoice> $abilities Выбранные способности.
     * @param array<int, CharacterInventoryChoice> $inventory Инвентарь.
     * @param int|null $budget limits.money или null, если потолка нет.
     *
     * @return array{money: int, problems: array<int, CharacterProblem>} Остаток и отказы.
     */
    public function getBalance(CharacterRuleSlice $slice, array $abilities, array $inventory, ?int $budget): array
    {
        $grants = $this->grantMoney($slice, $abilities);
        $shop = $this->shopCost($slice, $inventory);
        $pool = ($budget ?? 0) + $grants;
        $problems = $this->overspend($budget, $shop, $pool);

        return [
            'money' => max(0, $pool - $shop),
            'problems' => $problems,
        ];
    }

    /**
     * Перерасход только при заданном потолке.
     *
     * @param int|null $budget Потолок.
     * @param int $shop Сумма шопа.
     * @param int $pool Бюджет и гранты.
     *
     * @return array<int, CharacterProblem> Пусто, если уложились.
     */
    private function overspend(?int $budget, int $shop, int $pool): array
    {
        if ($budget === null || $shop <= $pool) {
            return [];
        }

        return [new CharacterProblem('CHARACTER_BUDGET', 'Shop cost exceeds the money budget', 'budget', 'limits.money')];
    }

    /**
     * Сумма fixed денежных грантов выбранных способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, CharacterAbilityChoice> $abilities Способности.
     *
     * @return int Сумма.
     */
    private function grantMoney(CharacterRuleSlice $slice, array $abilities): int
    {
        $total = 0;
        foreach ($abilities as $ability) {
            $total += $this->abilityMoney($slice, $ability);
        }

        return $total;
    }

    /**
     * Деньги одной способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     *
     * @return int Сумма.
     */
    private function abilityMoney(CharacterRuleSlice $slice, CharacterAbilityChoice $ability): int
    {
        $rule = $slice->findLive($ability->getRuleCode());
        if ($rule === null) {
            return 0;
        }

        return $this->sumGrants($this->donorGrants->getForLevel($rule, $ability->getRuleCode(), $ability->getLevel()));
    }

    /**
     * fixed грантов type money.
     *
     * @param array<int, CharacterDonorGrant> $grants Гранты.
     *
     * @return int Сумма.
     */
    private function sumGrants(array $grants): int
    {
        $total = 0;
        foreach ($grants as $row) {
            $grant = $row->getGrant();
            if ($grant instanceof MoneyGrant) {
                $total += $grant->getFixed();
            }
        }

        return $total;
    }

    /**
     * Сумма cost_gm × quantity. Innate и кастом без кода — 0.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, CharacterInventoryChoice> $inventory Инвентарь.
     *
     * @return int Сумма.
     */
    private function shopCost(CharacterRuleSlice $slice, array $inventory): int
    {
        $total = 0;
        foreach ($inventory as $item) {
            $total += $this->lineCost($slice, $item);
        }

        return $total;
    }

    /**
     * Цена одной строки инвентаря.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterInventoryChoice $item Выбор.
     *
     * @return int Цена.
     */
    private function lineCost(CharacterRuleSlice $slice, CharacterInventoryChoice $item): int
    {
        $rule = $slice->findLive($item->getRuleCode());
        if ($rule === null || $this->specReader->isInnateItem($rule)) {
            return 0;
        }

        return $this->specReader->getItemCostGm($rule) * max(0, $item->getQuantity());
    }
}
