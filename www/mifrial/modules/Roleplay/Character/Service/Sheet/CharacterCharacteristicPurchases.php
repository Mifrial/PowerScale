<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterCharacteristicPurchase;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceCharacteristic;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RacePurchaseLevel;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;

/**
 * Закупка характеристики должна попадать в лестницу режима purchased живой расы.
 */
final class CharacterCharacteristicPurchases
{
    /**
     * Пишет отказы закупки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return array<int, CharacterPurchasedCharacteristic> Ступени из правила.
     */
    public function resolve(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): array
    {
        $rows = [];
        $seen = [];
        foreach ($choices->getCharacteristicPurchases() as $index => $purchase) {
            $row = $this->resolveOne($slice, $choices->getRaceCode(), $purchase, $index, $seen, $problems);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Одна закупка. Нулевая цена — ступень не выбрана.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $raceCode Код расы.
     * @param CharacterCharacteristicPurchase $purchase Закупка.
     * @param int $index Индекс.
     * @param array<string, true> $seen Уже виденные коды.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return CharacterPurchasedCharacteristic|null Ступень или null.
     */
    private function resolveOne(
        CharacterRuleSlice $slice,
        ?string $raceCode,
        CharacterCharacteristicPurchase $purchase,
        int $index,
        array &$seen,
        CharacterProblemList $problems,
    ): ?CharacterPurchasedCharacteristic {
        if ($purchase->getCost() === 0) {
            return null;
        }

        $code = $purchase->getCharacteristicCode();
        $level = isset($seen[$code]) ? null : $this->levelOf($slice, $raceCode, $purchase);
        $seen[$code] = true;
        if ($level === null) {
            $problems->add('CHARACTER_PURCHASE', 'Characteristic purchase is not on the race ladder', 'requirement', 'characteristicPurchases.' . $index);

            return null;
        }

        return new CharacterPurchasedCharacteristic($code, $level->getCost(), $level->getValue());
    }

    /**
     * Ступень purchased с той же ценой. Цена и значение берутся из правила.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $raceCode Код расы.
     * @param CharacterCharacteristicPurchase $purchase Закупка.
     *
     * @return RacePurchaseLevel|null Ступень или null.
     */
    private function levelOf(CharacterRuleSlice $slice, ?string $raceCode, CharacterCharacteristicPurchase $purchase): ?RacePurchaseLevel
    {
        $row = $this->characteristic($slice, $raceCode, $purchase->getCharacteristicCode());
        if ($row === null || $row->getMode() !== 'purchased') {
            return null;
        }

        foreach ($row->getPurchase() as $level) {
            if ($level->getCost() === $purchase->getCost()) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Строка характеристики живой расы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $raceCode Код расы.
     * @param string $characteristicCode Код характеристики.
     *
     * @return RaceCharacteristic|null Строка или null.
     */
    private function characteristic(CharacterRuleSlice $slice, ?string $raceCode, string $characteristicCode): ?RaceCharacteristic
    {
        if ($raceCode === null) {
            return null;
        }

        $rule = $slice->findLive($raceCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof RaceSpec) {
            return null;
        }

        foreach ($spec->getCharacteristics() as $row) {
            if ($row->getCharacteristicCode() === $characteristicCode) {
                return $row;
            }
        }

        return null;
    }
}
