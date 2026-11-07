<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityParameter;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;

/**
 * Параметры формулы донора: выбор, раса, default spec.
 */
final class CharacterGrantParameters
{
    /**
     * Создаёт резолв параметров.
     *
     * @param CharacterRaceAbilityCodes $raceAbilityCodes Коды расы.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterRaceAbilityCodes $raceAbilityCodes,
    ) {
    }

    /**
     * Параметры формулы донора: выбор, затем раса, затем default spec.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterDonorGrant $grant Грант.
     *
     * @return array<string, int> Код → значение.
     */
    public function forGrant(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterDonorGrant $grant): array
    {
        $code = $grant->getDonorCode();
        foreach ($choices->getAbilities() as $ability) {
            if ($ability->getRuleCode() === $code && $ability->getParameters() !== []) {
                return $ability->getParameters() + $this->defaults($slice, $code);
            }
        }

        $fromRace = $this->raceAbilityCodes->getParameters($slice, $choices->getRaceCode(), $code);
        if ($fromRace !== []) {
            return $fromRace + $this->defaults($slice, $code);
        }

        return $this->defaults($slice, $code);
    }

    /**
     * Default скаляров spec способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Способность.
     *
     * @return array<string, int> Код → значение.
     */
    private function defaults(CharacterRuleSlice $slice, string $code): array
    {
        $spec = $slice->findLive($code)?->getSpec();
        if (
            !$spec instanceof AbilityPlainSpec
            && !$spec instanceof AbilityActionSpec
            && !$spec instanceof AbilitySpellSpec
            && !$spec instanceof AbilityProcessSpec
        ) {
            return [];
        }

        $values = [];
        foreach ($spec->getBase()->getParameters() as $parameter) {
            $scalar = $this->scalarDefault($parameter);
            if ($scalar !== null) {
                $values[$parameter->getCode()] = $scalar;
            }
        }

        return $values;
    }

    /**
     * Скаляр default. Размерный — null.
     *
     * @param AbilityParameter $parameter Параметр.
     *
     * @return int|null Значение.
     */
    private function scalarDefault(AbilityParameter $parameter): ?int
    {
        $default = $parameter->getDefault();
        if (is_int($default)) {
            return $default;
        }

        return $default->getSize() === 0 ? $default->getBase() : null;
    }
}
