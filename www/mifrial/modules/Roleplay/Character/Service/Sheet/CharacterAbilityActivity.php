<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Service\AbilityRequirementCheck;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Value\RequirementValues;

/**
 * Эффективный уровень способности по требованиям.
 */
final class CharacterAbilityActivity
{
    private readonly CharacterRequirementFacts $facts;

    private readonly AbilityRequirementCheck $check;

    /**
     * Собирает проверку.
     *
     * @return void
     */
    public function __construct()
    {
        $this->facts = new CharacterRequirementFacts();
        $this->check = new AbilityRequirementCheck();
    }

    /**
     * Уровни после проверки требований.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $chosen Купленные уровни.
     * @param array<string, DimensionalNumber> $characteristics Живые характеристики.
     * @param array<string, int|DimensionalNumber> $resourceLimits Лимиты ресурсов.
     * @param array<string, int> $requirementLevels Уровни для требований, включая производные.
     *
     * @return array<string, int> Код → уровень.
     */
    public function effectiveLevels(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $chosen,
        array $characteristics,
        array $resourceLimits,
        array $requirementLevels = [],
    ): array {
        $derived = $requirementLevels;
        $facts = $this->facts->forModel(
            $slice,
            $choices,
            $chosen + $derived,
            $characteristics,
            $resourceLimits,
        );
        $levels = $chosen;
        foreach ($choices->getAbilities() as $ability) {
            $code = $ability->getRuleCode();
            $wanted = $chosen[$code] ?? 0;
            $levels[$code] = $this->capped($slice, $code, $wanted, $facts->withLevels($levels + $derived));
        }

        return $levels;
    }

    /**
     * Максимальный уровень, на котором требования выполнены.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Способность.
     * @param int $wanted Заявленный уровень.
     * @param RequirementValues $facts Факты с текущими уровнями.
     *
     * @return int Уровень.
     */
    private function capped(
        CharacterRuleSlice $slice,
        string $code,
        int $wanted,
        RequirementValues $facts,
    ): int {
        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof AbilitySpec || $wanted < 1) {
            return max(0, $wanted);
        }

        $active = 0;
        for ($level = 1; $level <= $wanted; $level++) {
            if (!$this->blockPasses($spec, $level, $facts)) {
                break;
            }

            $active = $level;
        }

        return $active;
    }

    /**
     * Требования spec, если есть база.
     *
     * @param AbilitySpec $spec Способность.
     *
     * @return array<int, array{level: int, requirements: array<int, AbilityRequirement>}> Блоки.
     */
    private function requirementBlocks(AbilitySpec $spec): array
    {
        if (
            $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityActionSpec
            || $spec instanceof AbilitySpellSpec
            || $spec instanceof AbilityProcessSpec
        ) {
            return $spec->getBase()->getRequirements();
        }

        return [];
    }

    /**
     * Требования блока уровня. Неизвестные факты не гасят уровень.
     *
     * @param AbilitySpec $spec Способность.
     * @param int $level Уровень.
     * @param RequirementValues $facts Факты.
     *
     * @return bool true, если блок активен.
     */
    private function blockPasses(AbilitySpec $spec, int $level, RequirementValues $facts): bool
    {
        foreach ($this->requirementBlocks($spec) as $block) {
            if ($block['level'] !== $level) {
                continue;
            }

            foreach ($block['requirements'] as $requirement) {
                if ($this->check->check($requirement, $facts) === false) {
                    return false;
                }
            }
        }

        return true;
    }
}
