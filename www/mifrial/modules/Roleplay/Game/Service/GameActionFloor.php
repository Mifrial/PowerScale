<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Нижняя цена действия в ОД.
 *
 * Глобальный пол удара — константа с заделом на настройки ревизии, карточный
 * пол — `min_total_action_cost`. Пол поднимает только трату ОД.
 */
final class GameActionFloor
{
    public const MIN_STRIKE_AP = 2;

    /**
     * Поднимает трату ОД до пола.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $actionRuleCode Карточка.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param bool $strike Удар ли это.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    public function apply(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $spends,
        bool $strike,
    ): array {
        $floor = $this->floorOf($slice, $actionRuleCode, $strike);
        $turnCode = $this->turnResourceCode($slice);
        if ($floor < 1 || $turnCode === null) {
            return $spends;
        }

        foreach ($spends as $index => $spend) {
            if ($spend->getResourceCode() !== $turnCode) {
                continue;
            }

            $native = $spend->getAmount()->toNative();
            if (!is_int($native) || $native >= $floor) {
                return $spends;
            }

            $spends[$index] = new ResourceSpend($turnCode, new ScalarResourceValue($floor));

            return $spends;
        }

        $spends[] = new ResourceSpend($turnCode, new ScalarResourceValue($floor));

        return $spends;
    }

    /**
     * Поднимает сумму ОД до пола.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $actionRuleCode Карточка.
     * @param array<string, int> $amounts Цены по ресурсам.
     * @param bool $strike Удар ли это.
     *
     * @return array<string, int> Цены.
     */
    public function amounts(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $amounts,
        bool $strike,
    ): array {
        $floor = $this->floorOf($slice, $actionRuleCode, $strike);
        $turnCode = $this->turnResourceCode($slice);
        if ($floor < 1 || $turnCode === null) {
            return $amounts;
        }

        if (($amounts[$turnCode] ?? 0) < $floor) {
            $amounts[$turnCode] = $floor;
        }

        return $amounts;
    }

    /**
     * Пол карточки и удара.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param string $actionRuleCode Карточка.
     * @param bool $strike Удар ли это.
     *
     * @return int Пол.
     */
    public function floorOf(CharacterRuleSlice $slice, string $actionRuleCode, bool $strike): int
    {
        $spec = $actionRuleCode === '' ? null : $slice->findLive($actionRuleCode)?->getSpec();
        $card = $spec instanceof AbilityActionSpec ? $spec->getBase()->getMinTotalActionCost() : null;
        $floor = is_int($card) ? $card : 0;

        return $strike ? max($floor, self::MIN_STRIKE_AP) : $floor;
    }

    /**
     * Код ресурса turn refill.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string|null Код или null.
     */
    public function turnResourceCode(CharacterRuleSlice $slice): ?string
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                return $rule->getCode();
            }
        }

        return null;
    }
}
