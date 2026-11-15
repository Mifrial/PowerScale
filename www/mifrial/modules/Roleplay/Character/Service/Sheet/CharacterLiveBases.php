<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterGrantParameters;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicParameterGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Базы характеристик: раса, закупки, гранты и automatic.
 */
final class CharacterLiveBases
{
    /**
     * Создаёт расчёт баз.
     *
     * @param CharacterAbilityGrantReader $grantReader Гранты способностей.
     * @param CharacterGrantParameters $parameters Параметры формул грантов.
     * @param CharacterEquippedLive $equipped Предметы с параметрами экземпляра.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterAbilityGrantReader $grantReader,
        private readonly CharacterGrantParameters $parameters,
        private readonly CharacterEquippedLive $equipped,
    ) {
    }

    /**
     * Базы расы, закупок, грантов characteristic и automatic.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<string, CharacteristicNumber> Код → база.
     */
    public function compute(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
        array $abilityLevels,
    ): array {
        $bases = $this->raceBases($slice, $choices, $validation);
        $bases = $this->grantBases($bases, $slice, $choices, $abilityLevels);
        $bases = $this->itemBases($bases, $slice, $choices);

        return $this->automaticBases($bases, $slice);
    }

    /**
     * Гранты characteristic поверх баз расы.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $abilityLevels Уровни.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function grantBases(
        array $bases,
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $abilityLevels,
    ): array {
        foreach ($this->grantReader->grants($slice, $choices, $abilityLevels) as $grant) {
            $body = $grant->getGrant();
            if ($body instanceof CharacteristicGrant) {
                $bases[$body->getCharacteristicCode()] = $body->getValue();
                continue;
            }

            if ($body instanceof CharacteristicParameterGrant) {
                $values = $this->parameters->forGrant($slice, $choices, $grant);
                $value = $values[$body->getParameterCode()] ?? 0;
                $bases[$body->getCharacteristicCode()] = new CharacteristicNumber($value * $body->getPerUnit(), 0);
            }
        }

        return $bases;
    }

    /**
     * Базы от грантов предметов с параметрами экземпляра.
     *
     * Ядро даёт `magic-power` от своего `power`; при нескольких ядрах
     * побеждает лучшая база, значения не складываются.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function itemBases(array $bases, CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        foreach ($this->equipped->equippedOrInnate($slice, $choices) as $item) {
            foreach ($item['spec']->getGrants() as $grant) {
                if (!$grant instanceof CharacteristicParameterGrant) {
                    continue;
                }

                $value = $item['parameters'][$grant->getParameterCode()] ?? null;
                if (!$value instanceof DimensionalNumber) {
                    continue;
                }

                $number = new CharacteristicNumber(
                    $value->getBase() * $grant->getPerUnit(),
                    $value->getSize(),
                );
                $code = $grant->getCharacteristicCode();
                $current = $bases[$code] ?? null;
                if (!$current instanceof CharacteristicNumber || $current->characteristicCompare($number) < 0) {
                    $bases[$code] = $number;
                }
            }
        }

        return $bases;
    }

    /**
     * Значения automatic из спецификаций характеристик.
     *
     * @param array<string, CharacteristicNumber> $bases Базы.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function automaticBases(array $bases, CharacterRuleSlice $slice): array
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->getSpec();
            if (!$spec instanceof CharacteristicSpec) {
                continue;
            }

            $automatic = $spec->getAutomatic();
            if ($automatic === false || isset($bases[$rule->getCode()])) {
                continue;
            }

            $bases[$rule->getCode()] = $automatic instanceof CharacteristicNumber
                ? $automatic
                : new CharacteristicNumber(3, 0);
        }

        return $bases;
    }

    /**
     * Базы расы: fixed или купленная ступень / минимум.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterValidation $validation Снимок.
     *
     * @return array<string, CharacteristicNumber> Базы.
     */
    private function raceBases(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterValidation $validation,
    ): array {
        $race = $slice->findLive((string) $choices->getRaceCode())?->getSpec();
        if (!$race instanceof RaceSpec) {
            return [];
        }

        $purchased = [];
        foreach ($validation->getPurchasedCharacteristics() as $row) {
            $purchased[$row->getCharacteristicCode()] = $row;
        }

        $bases = [];
        foreach ($race->getCharacteristics() as $row) {
            $code = $row->getCharacteristicCode();
            if ($row->getMode() === 'fixed') {
                $bases[$code] = $row->getBase();
                continue;
            }

            $bases[$code] = ($purchased[$code] ?? null) instanceof CharacterPurchasedCharacteristic
                ? $purchased[$code]->getValue()
                : $row->getBase();
        }

        return $bases;
    }
}
