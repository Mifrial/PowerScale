<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Смягчение одного удара: закупка характеристики уклонения и польза профиля.
 */
final class GameStrikeSoaks
{
    /**
     * Сдвигает закупку единственной живой характеристики и возвращает пару.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param FormulaContext $context Контекст характеристик.
     * @param int $benefit Польза профиля.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws GameInvalidException Если карточки нет, их две или значения нет.
     */
    public function shift(
        CharacterRuleSlice $slice,
        FormulaContext $context,
        int $benefit,
    ): DimensionalNumber {
        $value = $context->findCharacteristic($this->findCode($slice));
        if ($value === null) {
            throw new GameInvalidException('Game strike dodge soak is invalid');
        }

        return $value->modify($benefit);
    }

    /**
     * Код единственной живой характеристики уклонения.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string Код карточки.
     *
     * @throws GameInvalidException Если карточек нет или их несколько.
     */
    private function findCode(CharacterRuleSlice $slice): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof CharacteristicSpec && $spec->isDodgeSoak()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike dodge soak is invalid');
        }

        return $matched[0];
    }
}
