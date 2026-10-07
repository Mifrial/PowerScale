<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Делит повреждение на стойкость и собирает putDamageSplit.
 */
final class GameStrikeSplits
{
    /**
     * Одна операция остатка и частного. Лист не пишет.
     *
     * @param CharacterRuleSlice $slice Срез ревизии игры.
     * @param ICharacterFormulaContexts $contexts Разбор листа.
     * @param array<string, mixed> $sheet Лист цели.
     * @param DimensionalNumber $injury Повреждение.
     *
     * @return array{kind: string, remainder: array{base: int, size: int}, quotient: int} Операция.
     *
     * @throws GameInvalidException Если карточки нет, их две, закупки нет или деление отвергнуто.
     */
    public function operation(
        CharacterRuleSlice $slice,
        ICharacterFormulaContexts $contexts,
        array $sheet,
        DimensionalNumber $injury,
    ): array {
        $endurance = $this->endurance($slice, $contexts, $sheet);
        try {
            $split = $injury->divide($endurance);
        } catch (RuleInvalidException $exception) {
            throw new GameInvalidException('Game strike endurance is invalid', $exception);
        }

        $remainder = $split->getRemainder();

        return [
            'kind' => 'putDamageSplit',
            'remainder' => ['base' => $remainder->getBase(), 'size' => $remainder->getSize()],
            'quotient' => $split->getQuotient(),
        ];
    }

    /**
     * Пара единственной живой стойкости.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param ICharacterFormulaContexts $contexts Разбор листа.
     * @param array<string, mixed> $sheet Лист цели.
     *
     * @return DimensionalNumber Закупка.
     *
     * @throws GameInvalidException Если карточки нет, их две или закупки нет.
     */
    private function endurance(
        CharacterRuleSlice $slice,
        ICharacterFormulaContexts $contexts,
        array $sheet,
    ): DimensionalNumber {
        try {
            $value = $contexts->build($sheet)->findCharacteristic($this->findCode($slice));
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike endurance is invalid', $exception);
        }

        if ($value === null) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $value;
    }

    /**
     * Код единственной живой характеристики стойкости.
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
            if ($spec instanceof CharacteristicSpec && $spec->isDamageEndurance()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $matched[0];
    }
}
