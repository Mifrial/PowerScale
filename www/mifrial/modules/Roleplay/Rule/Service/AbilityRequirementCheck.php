<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Service;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\AndRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CharacteristicValueRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\CurrentSpeedRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasAbilityRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasKeywordRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\HasMagicPathRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MagicPathExperienceRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MinWeaponMasteryRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\MinWeaponMasteryValueRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\OrRequirement;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Requirement\ResourceLimitRequirement;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use Mifrial\Roleplay\Rule\Value\RequirementValues;

/**
 * Трёхзначная проверка требований способности: выполнено, не выполнено, данных нет.
 */
final class AbilityRequirementCheck
{
    /**
     * Проверяет требование против фактов.
     *
     * @param AbilityRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     * @param string|null $domainContext Путь текущего экземпляра или null.
     *
     * @return bool|null true, false или null, если данных нет.
     */
    public function check(
        AbilityRequirement $requirement,
        RequirementValues $values,
        ?string $domainContext = null,
    ): ?bool {
        if ($requirement instanceof AndRequirement) {
            return $this->all($requirement->getRequirements(), $values, $domainContext);
        }

        if ($requirement instanceof OrRequirement) {
            return $this->any($requirement->getRequirements(), $values, $domainContext);
        }

        return $this->leaf($requirement, $values, $domainContext);
    }

    /**
     * Простое требование без логических групп.
     *
     * @param AbilityRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     * @param string|null $domainContext Путь текущего экземпляра или null.
     *
     * @return bool|null Результат.
     */
    private function leaf(
        AbilityRequirement $requirement,
        RequirementValues $values,
        ?string $domainContext,
    ): ?bool {
        return match (true) {
            $requirement instanceof HasAbilityRequirement => $this->hasAbility($requirement, $values, $domainContext),
            $requirement instanceof CharacteristicValueRequirement => $this->characteristicPasses(
                $requirement,
                $values,
            ),
            $requirement instanceof ResourceLimitRequirement => $this->resourcePasses($requirement, $values),
            default => $this->traitRequirement($requirement, $values),
        };
    }

    /**
     * Уровень способности не ниже минимума и домен пути, если он задан.
     *
     * @param HasAbilityRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     * @param string|null $domainContext Путь текущего экземпляра или null.
     *
     * @return bool Результат.
     */
    private function hasAbility(
        HasAbilityRequirement $requirement,
        RequirementValues $values,
        ?string $domainContext,
    ): bool {
        $code = $requirement->getAbilityCode();
        $level = $values->numbers->levels[$code] ?? 0;
        if ($level < ($requirement->getMinLevel() ?? 1)) {
            return false;
        }

        if ($domainContext === null || $domainContext === '') {
            return true;
        }

        $domains = $values->traits->abilityDomains[$code] ?? [];
        if ($domains === []) {
            return true;
        }

        $covers = $values->traits->pathCovers[$domainContext] ?? [$domainContext];

        return array_intersect($domains, $covers) !== [];
    }

    /**
     * Требования признаков и владения оружием.
     *
     * @param AbilityRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool|null Результат.
     */
    private function traitRequirement(AbilityRequirement $requirement, RequirementValues $values): ?bool
    {
        return match (true) {
            $requirement instanceof HasAbilityKeywordRequirement => $this->keywordCount($requirement, $values)
                >= $requirement->getMinCount(),
            $requirement instanceof HasKeywordRequirement => in_array(
                $requirement->getKeywordCode(),
                $values->traits->keywords,
                true,
            ),
            $requirement instanceof MinWeaponMasteryRequirement => $this->weaponMasteryPasses($requirement, $values),
            $requirement instanceof MinWeaponMasteryValueRequirement => $this->weaponMasteryValuePasses(
                $requirement,
                $values,
            ),
            default => $this->unmodelled($requirement, $values),
        };
    }

    /**
     * Требования скорости и магии: null, если модели нет.
     *
     * @param AbilityRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool|null Результат.
     */
    private function unmodelled(AbilityRequirement $requirement, RequirementValues $values): ?bool
    {
        return match (true) {
            $requirement instanceof CurrentSpeedRequirement => $this->speedPasses($requirement, $values),
            $requirement instanceof HasMagicPathRequirement => $this->magicPathPasses($requirement, $values),
            $requirement instanceof MagicPathExperienceRequirement => $this->magicExperiencePasses(
                $requirement,
                $values,
            ),
            default => null,
        };
    }

    /**
     * Путь волшебства есть.
     *
     * @param HasMagicPathRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool|null Результат или null, если модели нет.
     */
    private function magicPathPasses(
        HasMagicPathRequirement $requirement,
        RequirementValues $values,
    ): ?bool {
        $paths = $values->unmodelled->magicPaths;

        return $paths === null ? null : in_array($requirement->getPathCode(), $paths, true);
    }

    /**
     * Опыт пути волшебства не ниже минимума.
     *
     * @param MagicPathExperienceRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool|null Результат или null, если модели нет.
     */
    private function magicExperiencePasses(
        MagicPathExperienceRequirement $requirement,
        RequirementValues $values,
    ): ?bool {
        $experience = $values->unmodelled->magicPathExperience;

        return $experience === null
            ? null
            : ($experience[$requirement->getPathCode()] ?? 0) >= $requirement->getMin();
    }

    /**
     * Неявное И с сохранением неизвестного.
     *
     * @param array<int, AbilityRequirement> $requirements Дети.
     * @param RequirementValues $values Факты.
     * @param string|null $domainContext Путь текущего экземпляра или null.
     *
     * @return bool|null Результат.
     */
    private function all(
        array $requirements,
        RequirementValues $values,
        ?string $domainContext,
    ): ?bool {
        if ($requirements === []) {
            return true;
        }

        $unknown = false;
        foreach ($requirements as $requirement) {
            $result = $this->check($requirement, $values, $domainContext);
            if ($result === false) {
                return false;
            }

            $unknown = $unknown || $result === null;
        }

        return $unknown ? null : true;
    }

    /**
     * Или с сохранением неизвестного. Пустой список — false.
     *
     * @param array<int, AbilityRequirement> $requirements Дети.
     * @param RequirementValues $values Факты.
     * @param string|null $domainContext Путь текущего экземпляра или null.
     *
     * @return bool|null Результат.
     */
    private function any(
        array $requirements,
        RequirementValues $values,
        ?string $domainContext,
    ): ?bool {
        $unknown = false;
        foreach ($requirements as $requirement) {
            $result = $this->check($requirement, $values, $domainContext);
            if ($result === true) {
                return true;
            }

            $unknown = $unknown || $result === null;
        }

        return $unknown ? null : false;
    }

    /**
     * Характеристика не ниже минимума.
     *
     * @param CharacteristicValueRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool Результат.
     */
    private function characteristicPasses(
        CharacteristicValueRequirement $requirement,
        RequirementValues $values,
    ): bool {
        $value = $values->numbers->characteristics[$requirement->getCharacteristicCode()] ?? null;

        return $value !== null && $value->compare($requirement->getMin()) >= 0;
    }

    /**
     * Наличие лимита ресурса и его минимум.
     *
     * @param ResourceLimitRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool Результат.
     */
    private function resourcePasses(ResourceLimitRequirement $requirement, RequirementValues $values): bool
    {
        $code = $requirement->getResourceCode();
        if (!array_key_exists($code, $values->numbers->resourceLimits)) {
            return false;
        }

        $min = $requirement->getMin();

        return $min === null || $this->limitAtLeast($values->numbers->resourceLimits[$code], $min);
    }

    /**
     * Лимит не ниже минимума одного вида.
     *
     * @param int|DimensionalNumber $have Лимит.
     * @param int|DimensionalNumber $min Минимум.
     *
     * @return bool Результат.
     */
    private function limitAtLeast(int|DimensionalNumber $have, int|DimensionalNumber $min): bool
    {
        if (is_int($have) && is_int($min)) {
            return $have >= $min;
        }

        if ($have instanceof DimensionalNumber && $min instanceof DimensionalNumber) {
            return $have->compare($min) >= 0;
        }

        return false;
    }

    /**
     * Число живых способностей с признаком без исключённых.
     *
     * @param HasAbilityKeywordRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return int Число.
     */
    private function keywordCount(HasAbilityKeywordRequirement $requirement, RequirementValues $values): int
    {
        $count = 0;
        foreach ($values->numbers->levels as $code => $level) {
            if ($level > 0 && $this->abilityHasKeyword($code, $requirement, $values)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Способность имеет нужный признак и ни одного исключённого.
     *
     * @param string $code Код способности.
     * @param HasAbilityKeywordRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool Результат.
     */
    private function abilityHasKeyword(
        string $code,
        HasAbilityKeywordRequirement $requirement,
        RequirementValues $values,
    ): bool {
        $keywords = $values->traits->abilityKeywords[$code] ?? [];
        if (!in_array($requirement->getKeywordCode(), $keywords, true)) {
            return false;
        }

        return array_intersect($requirement->getExcludeKeywordCodes(), $keywords) === [];
    }

    /**
     * Хотя бы одна семья с признаком нужного уровня владения.
     *
     * @param MinWeaponMasteryRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool Результат.
     */
    private function weaponMasteryPasses(
        MinWeaponMasteryRequirement $requirement,
        RequirementValues $values,
    ): bool {
        foreach ($values->traits->weaponFamiliesByKeyword as $family => $keywords) {
            if (!in_array($requirement->getKeywordCode(), $keywords, true)) {
                continue;
            }

            if (($values->traits->weaponMasteryLevels[$family] ?? 0) >= $requirement->getMinLevel()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Хотя бы одна семья с признаком и мастерством не ниже минимума.
     *
     * @param MinWeaponMasteryValueRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool Результат.
     */
    private function weaponMasteryValuePasses(
        MinWeaponMasteryValueRequirement $requirement,
        RequirementValues $values,
    ): bool {
        foreach ($values->traits->weaponFamiliesByKeyword as $family => $keywords) {
            if (!in_array($requirement->getKeywordCode(), $keywords, true)) {
                continue;
            }

            $mastery = $values->traits->weaponMasteryValues[$family] ?? null;
            if ($mastery !== null && $mastery->compare($requirement->getMin()) >= 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ось скорости, направление и шаги.
     *
     * @param CurrentSpeedRequirement $requirement Требование.
     * @param RequirementValues $values Факты.
     *
     * @return bool|null Результат или null, если модели скорости нет.
     */
    private function speedPasses(CurrentSpeedRequirement $requirement, RequirementValues $values): ?bool
    {
        if ($values->unmodelled->speed === null) {
            return null;
        }

        $component = $values->unmodelled->speed[$requirement->getAxis()] ?? null;
        if (!is_array($component)) {
            return false;
        }

        return $component['direction'] === $requirement->getDirection()
            && $component['stepsPerActionPoint'] >= $requirement->getMinStepsPerActionPoint();
    }
}
