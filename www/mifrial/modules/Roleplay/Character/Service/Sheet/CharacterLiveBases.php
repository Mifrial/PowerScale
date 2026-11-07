<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterPurchasedCharacteristic;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Базы характеристик: раса, закупки, гранты и automatic.
 */
final class CharacterLiveBases
{
    /**
     * Создаёт расчёт баз.
     *
     * @param CharacterAbilityGrantReader $grantReader Гранты способностей.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterAbilityGrantReader $grantReader,
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
