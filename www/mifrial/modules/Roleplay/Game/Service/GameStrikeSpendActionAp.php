<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Базовая стоимость действия в ОД и код ресурса поворота.
 */
final class GameStrikeSpendActionAp
{
    /**
     * Базовая стоимость действия в ОД.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Карточка.
     * @param bool $strike Удар ли это.
     *
     * @return int Стоимость.
     */
    public function get(CharacterRuleSlice $slice, string $ruleCode, bool $strike = false): int
    {
        if ($ruleCode === '') {
            return 0;
        }

        $spec = $slice->findLive($ruleCode)?->getSpec();
        if (!$spec instanceof AbilityActionSpec) {
            return 0;
        }

        $sum = $this->sum($spec, (new GameActionFloor())->turnResourceCode($slice));

        return $strike ? max($sum, (new GameActionFloor())->floorOf($slice, $ruleCode, true)) : $sum;
    }

    /**
     * Сумма компонентов стоимости.
     *
     * @param AbilityActionSpec $spec Карточка действия.
     * @param string|null $turnCode Код ресурса поворота.
     *
     * @return int Сумма.
     */
    private function sum(AbilityActionSpec $spec, ?string $turnCode): int
    {
        $sum = 0;
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent) {
                continue;
            }

            if ($turnCode !== null && $component->getResourceCode() !== $turnCode) {
                continue;
            }

            $sum += $this->amount($component->getAmount());
        }

        return $sum;
    }

    /**
     * Число из компонента стоимости.
     *
     * @param mixed $amount Сумма.
     *
     * @return int Число.
     */
    private function amount(mixed $amount): int
    {
        if (is_int($amount)) {
            return $amount;
        }

        return $amount instanceof DimensionalNumber ? $amount->getBase() : 0;
    }
}
