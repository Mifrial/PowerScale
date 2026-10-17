<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceAbilityRef;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\SpeciesSpec;

/**
 * Коды способностей выбранной расы и её предков-видов.
 */
final class CharacterRaceAbilityCodes
{
    /**
     * Коды каталога расы. Нет живой расы — пустой список.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $raceCode Код расы.
     *
     * @return array<int, string> Коды.
     */
    public function getCodes(CharacterRuleSlice $slice, ?string $raceCode): array
    {
        if ($raceCode === null) {
            return [];
        }

        $race = $slice->findLive($raceCode);
        if ($race === null || $race->getType() !== 'race') {
            return [];
        }

        return $this->collectCodes($slice, $race->getCode(), $race->getType(), []);
    }

    /**
     * Скаляры параметров ссылки расы на способность. Нет ссылки — пусто.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $raceCode Код расы.
     * @param string $abilityCode Способность.
     *
     * @return array<string, int> Код → значение.
     */
    public function getParameters(CharacterRuleSlice $slice, ?string $raceCode, string $abilityCode): array
    {
        if ($raceCode === null) {
            return [];
        }

        foreach ($this->collectRefs($slice, $raceCode, $slice->findLive($raceCode)?->getType() ?? 'race', []) as $ref) {
            if ($ref->getAbilityCode() !== $abilityCode) {
                continue;
            }

            return $this->scalars($ref);
        }

        return [];
    }

    /**
     * Обходит расу и предков-видов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Текущий код.
     * @param string $expectedType Ожидаемый тип.
     * @param array<string, true> $seen Уже пройденные.
     *
     * @return array<int, string> Коды способностей.
     */
    private function collectCodes(CharacterRuleSlice $slice, string $code, string $expectedType, array $seen): array
    {
        $codes = [];
        if (!isset($seen[$code])) {
            $rule = $slice->findLive($code);
            $seen[$code] = true;
            $codes = $this->codesFrom($rule, $expectedType);
            $parent = $this->parentCode($rule);
            if ($rule !== null && $rule->getType() === $expectedType && is_string($parent) && $parent !== '') {
                $codes = array_merge($codes, $this->collectCodes($slice, $parent, 'species', $seen));
            }
        }

        return $codes;
    }

    /**
     * Коды ссылок, если тип совпал.
     *
     * @param CharacterResolvedRule|null $rule Правило или null.
     * @param string $expectedType Ожидаемый тип.
     *
     * @return array<int, string> Коды.
     */
    private function codesFrom(?CharacterResolvedRule $rule, string $expectedType): array
    {
        if ($rule === null || $rule->getType() !== $expectedType) {
            return [];
        }

        return $this->codesOf($rule);
    }

    /**
     * Коды ссылок одного правила.
     *
     * @param CharacterResolvedRule $rule Правило.
     *
     * @return array<int, string> Коды.
     */
    private function codesOf(CharacterResolvedRule $rule): array
    {
        if ($rule->isSpecBroken()) {
            return [];
        }

        $spec = $rule->getSpec();
        if (!$spec instanceof RaceSpec && !$spec instanceof SpeciesSpec) {
            return [];
        }

        $codes = [];
        foreach ($spec->getAbilities() as $ref) {
            if ($ref->getAbilityCode() !== '') {
                $codes[] = $ref->getAbilityCode();
            }
        }

        return $codes;
    }

    /**
     * Код предка расы или вида.
     *
     * @param CharacterResolvedRule|null $rule Правило.
     *
     * @return string|null Код или null.
     */
    private function parentCode(?CharacterResolvedRule $rule): ?string
    {
        if ($rule === null || $rule->isSpecBroken()) {
            return null;
        }

        $spec = $rule->getSpec();
        if ($spec instanceof RaceSpec || $spec instanceof SpeciesSpec) {
            return $spec->getParentRaceCode();
        }

        return null;
    }

    /**
     * Ссылки расы и предков, ребёнок раньше родителя.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Текущий код.
     * @param string $expectedType Ожидаемый тип.
     * @param array<string, true> $seen Уже пройденные.
     *
     * @return array<int, RaceAbilityRef> Ссылки.
     */
    private function collectRefs(CharacterRuleSlice $slice, string $code, string $expectedType, array $seen): array
    {
        $refs = [];
        if (isset($seen[$code])) {
            return $refs;
        }

        $seen[$code] = true;
        $rule = $slice->findLive($code);
        $spec = $rule !== null && !$rule->isSpecBroken() ? $rule->getSpec() : null;
        $matches = $rule !== null && $rule->getType() === $expectedType;
        if ($matches && ($spec instanceof RaceSpec || $spec instanceof SpeciesSpec)) {
            $refs = $spec->getAbilities();
            $parent = $spec->getParentRaceCode();
            if (is_string($parent) && $parent !== '') {
                $refs = array_merge($refs, $this->collectRefs($slice, $parent, 'species', $seen));
            }
        }

        return $refs;
    }

    /**
     * Скаляры ссылки: size 0 → base.
     *
     * @param RaceAbilityRef $ref Ссылка.
     *
     * @return array<string, int> Код → значение.
     */
    private function scalars(RaceAbilityRef $ref): array
    {
        $values = [];
        foreach ($ref->getParameters() as $code => $number) {
            if ($number->getSize() === 0) {
                $values[$code] = $number->getBase();
            }
        }

        return $values;
    }
}
