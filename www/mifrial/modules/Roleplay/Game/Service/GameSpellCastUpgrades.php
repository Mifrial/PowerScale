<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastAppliedUpgrade;
use Mifrial\Roleplay\Game\Dto\GameSpellCastUpgradeResolution;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellUpgrade;

/**
 * Разрешение улучшений каста по форме spell_upgrade.
 */
final class GameSpellCastUpgrades
{
    /**
     * Проверяет выбранные улучшения и собирает их эффект.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $abilityLevels Уровни способностей.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь каста.
     * @param array<int, array{code: string, value: int|null}> $selected Выбранные улучшения.
     *
     * @return GameSpellCastUpgradeResolution Разрешение.
     *
     * @throws GameInvalidException Если улучшение чужое, неизученное или неподдержано.
     */
    public function resolve(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $abilityLevels,
        string $spellCode,
        string $pathCode,
        array $selected,
    ): GameSpellCastUpgradeResolution {
        $applied = [];
        $advantages = [];
        $seen = [];
        foreach ($selected as $row) {
            $code = $row['code'];
            if (isset($seen[$code])) {
                throw new GameInvalidException('Game spell upgrade is selected twice');
            }

            $seen[$code] = true;
            $rule = $this->ruleOf($slice, $code);
            $spec = $this->specOf($rule);
            $upgrade = $this->upgradeOf($spec);
            if (!$this->supports($upgrade)) {
                throw new GameInvalidException('Game spell upgrade is not supported');
            }

            $this->assertLevel($abilityLevels, $code);
            $this->assertApplicable(
                $upgrade,
                $spec->getBase()->getParentAbilityCode(),
                $this->instancesOf($model, $code),
                $spellCode,
                $pathCode,
            );

            $value = $this->valueOf($upgrade, $row['value'] ?? null);
            $applied[] = $this->appliedOf($rule, $upgrade, $value);

            $advantage = $upgrade->getCheckAdvantage() ?? 0;
            if ($advantage !== 0) {
                $advantages[] = new RollAdvantage($code, $advantage, $rule->getName());
            }
        }

        return new GameSpellCastUpgradeResolution($applied, $advantages);
    }

    /**
     * Живая карточка улучшения.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return CharacterResolvedRule Карточка.
     *
     * @throws GameInvalidException Если карточки нет или spec битый.
     */
    private function ruleOf(CharacterRuleSlice $slice, string $code): CharacterResolvedRule
    {
        $rule = $slice->findLive($code);
        if ($rule === null || $rule->isSpecBroken()) {
            throw new GameInvalidException('Game spell upgrade is unknown');
        }

        return $rule;
    }

    /**
     * Форма способности.
     *
     * @param CharacterResolvedRule $rule Карточка.
     *
     * @return AbilityWithBase Форма.
     *
     * @throws GameInvalidException Если форма карточки чужая.
     */
    private function specOf(CharacterResolvedRule $rule): AbilityWithBase
    {
        $spec = $rule->getSpec();
        if (!$spec instanceof AbilityWithBase) {
            throw new GameInvalidException('Game spell upgrade is invalid');
        }

        return $spec;
    }

    /**
     * Улучшение по форме карточки.
     *
     * @param AbilityWithBase $spec Форма способности.
     *
     * @return SpellUpgrade Улучшение.
     *
     * @throws GameInvalidException Если улучшения нет.
     */
    private function upgradeOf(AbilityWithBase $spec): SpellUpgrade
    {
        $upgrade = $spec->getBase()->getSpellUpgrade();
        if ($upgrade === null) {
            throw new GameInvalidException('Game spell upgrade is invalid');
        }

        return $upgrade;
    }

    /**
     * Runtime исполняет все примитивы улучшения.
     *
     * @param SpellUpgrade $upgrade Улучшение.
     *
     * @return bool true, если поддерживается.
     */
    private function supports(SpellUpgrade $upgrade): bool
    {
        return $upgrade->getChain() === null && $upgrade->getChargeCap() === null;
    }

    /**
     * Уровень способности у кастера.
     *
     * @param array<string, mixed> $abilityLevels Уровни.
     * @param string $code Код.
     *
     * @return void
     *
     * @throws GameInvalidException Если уровня нет.
     */
    private function assertLevel(array $abilityLevels, string $code): void
    {
        $level = $abilityLevels[$code] ?? null;
        if (!is_int($level) || $level < 1) {
            throw new GameInvalidException('Game spell upgrade is not learned');
        }
    }

    /**
     * Экземпляры способности с ненулевым уровнем.
     *
     * @param CharacterChoices $model Выборы кастера.
     * @param string $code Код.
     *
     * @return array<int, CharacterAbilityChoice> Экземпляры.
     *
     * @throws GameInvalidException Если экземпляров нет.
     */
    private function instancesOf(CharacterChoices $model, string $code): array
    {
        $instances = [];
        foreach ($model->getAbilities() as $ability) {
            if ($ability->getRuleCode() === $code && $ability->getLevel() >= 1) {
                $instances[] = $ability;
            }
        }

        if ($instances === []) {
            throw new GameInvalidException('Game spell upgrade is not learned');
        }

        return $instances;
    }

    /**
     * Улучшение применимо к заклинанию и пути.
     *
     * @param SpellUpgrade $upgrade Улучшение.
     * @param string|null $parent Родитель заклинания.
     * @param array<int, CharacterAbilityChoice> $instances Экземпляры.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь каста.
     *
     * @return void
     *
     * @throws GameInvalidException Если улучшение чужое.
     */
    private function assertApplicable(
        SpellUpgrade $upgrade,
        ?string $parent,
        array $instances,
        string $spellCode,
        string $pathCode,
    ): void {
        if ($parent !== null && $parent !== '') {
            if ($parent !== $spellCode) {
                throw new GameInvalidException('Game spell upgrade belongs to another spell');
            }

            foreach ($instances as $instance) {
                $domain = $instance->getDomainCode();
                if ($upgrade->isAnyPath() || $domain === '' || $domain === $pathCode) {
                    return;
                }
            }

            throw new GameInvalidException('Game spell upgrade belongs to another path');
        }

        foreach ($instances as $instance) {
            $domain = $instance->getDomainCode();
            if ($domain === '') {
                continue;
            }

            if ($upgrade->isAnyPath() || $domain === $pathCode) {
                return;
            }
        }

        throw new GameInvalidException('Game spell upgrade belongs to another path');
    }

    /**
     * Выбранное значение шага.
     *
     * @param SpellUpgrade $upgrade Улучшение.
     * @param int|null $value Значение из запроса.
     *
     * @return int Значение.
     *
     * @throws GameInvalidException Если значение недопустимо.
     */
    private function valueOf(SpellUpgrade $upgrade, ?int $value): int
    {
        if ($value !== null && $value < 0) {
            throw new GameInvalidException('Game spell upgrade value is invalid');
        }

        if ($upgrade->getResistancePenetrationPerStep() === null) {
            if ($value !== null && $value !== 0) {
                throw new GameInvalidException('Game spell upgrade value is not allowed');
            }

            return 0;
        }

        return $value ?? 1;
    }

    /**
     * Запись применённого улучшения.
     *
     * @param CharacterResolvedRule $rule Карточка.
     * @param SpellUpgrade $upgrade Улучшение.
     * @param int $value Значение шага.
     *
     * @return GameSpellCastAppliedUpgrade Запись.
     */
    private function appliedOf(
        CharacterResolvedRule $rule,
        SpellUpgrade $upgrade,
        int $value,
    ): GameSpellCastAppliedUpgrade {
        $perStep = $upgrade->getResistancePenetrationPerStep();

        return new GameSpellCastAppliedUpgrade(
            $rule->getCode(),
            $rule->getName(),
            $value,
            $upgrade->getActionPointDelta(),
            $upgrade->getCheckAdvantage() ?? 0,
            $perStep === null ? 0 : $value * $perStep,
            $perStep === null ? 0 : $value,
        );
    }
}
