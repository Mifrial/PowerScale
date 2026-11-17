<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterStudyPaths;
use Mifrial\Roleplay\Game\Dto\GameSpellCastAppliedUpgrade;
use Mifrial\Roleplay\Game\Dto\GameSpellCastUpgradeResolution;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AttackHit;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellUpgrade;

/**
 * Разрешение улучшений каста по форме spell_upgrade.
 */
final class GameSpellCastUpgrades
{
    private readonly CharacterStudyPaths $paths;

    private readonly GameSpellChainHop $chain;

    /**
     * Создаёт разрешение улучшений.
     *
     * @return void
     */
    public function __construct()
    {
        $this->paths = new CharacterStudyPaths();
        $this->chain = new GameSpellChainHop();
    }

    /**
     * Проверяет выбранные улучшения и собирает их эффект.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $model Выборы кастера.
     * @param array<string, mixed> $abilityLevels Уровни способностей.
     * @param string $spellCode Заклинание.
     * @param string $pathCode Путь каста.
     * @param array<int, array{code: string, value: int|null}> $selected Выбранные улучшения.
     * @param array<int, string>|null $recordedCodes Коды улучшений записи источника; null — каст по изученным.
     *
     * @return GameSpellCastUpgradeResolution Разрешение.
     *
     * @throws GameInvalidException Если улучшение чужое, неизученное, незаписанное или неподдержано.
     */
    public function resolve(
        CharacterRuleSlice $slice,
        CharacterChoices $model,
        array $abilityLevels,
        string $spellCode,
        string $pathCode,
        array $selected,
        ?array $recordedCodes = null,
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

            $chain = $upgrade->getChain();
            if ($chain !== null) {
                $this->chain->assertSupported($chain);
            }

            $parent = $spec->getBase()->getParentAbilityCode();
            if ($recordedCodes === null) {
                $this->assertLevel($abilityLevels, $code);
                $this->assertApplicable(
                    $slice,
                    $upgrade,
                    $parent,
                    $this->instancesOf($model, $code),
                    $spellCode,
                    $pathCode,
                );
            } else {
                $this->assertRecorded($slice, $upgrade, $recordedCodes, $code, $parent, $spellCode);
            }

            $value = $this->valueOf($upgrade, $row['value'] ?? null);
            $applied[] = $this->appliedOf($rule, $upgrade, $value);

            $advantage = $upgrade->getCheckAdvantage() ?? 0;
            if ($advantage !== 0) {
                $advantages[] = new RollAdvantage(
                    $upgrade->getCheckAdvantageSource() ?? $code,
                    $advantage,
                    $rule->getName(),
                );
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
        return $upgrade->getChargeCap() === null;
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
     * Улучшение записано в источнике и принадлежит этому заклинанию.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param SpellUpgrade $upgrade Улучшение.
     * @param array<int, string> $recordedCodes Коды записи.
     * @param string $code Код улучшения.
     * @param string|null $parent Родитель заклинания.
     * @param string $spellCode Заклинание каста.
     *
     * @return void
     *
     * @throws GameInvalidException Если улучшения нет в записи или оно чужое.
     */
    private function assertRecorded(
        CharacterRuleSlice $slice,
        SpellUpgrade $upgrade,
        array $recordedCodes,
        string $code,
        ?string $parent,
        string $spellCode,
    ): void {
        if (!in_array($code, $recordedCodes, true)) {
            throw new GameInvalidException('Game spell upgrade is not recorded');
        }

        if ($parent !== null && $parent !== '' && $parent !== $spellCode) {
            throw new GameInvalidException('Game spell upgrade belongs to another spell');
        }

        $this->assertSpellKeywords($slice, $upgrade, $spellCode);
        $this->assertRangeApplicable($slice, $upgrade, $spellCode);
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
        CharacterRuleSlice $slice,
        SpellUpgrade $upgrade,
        ?string $parent,
        array $instances,
        string $spellCode,
        string $pathCode,
    ): void {
        $this->assertSpellKeywords($slice, $upgrade, $spellCode);
        $this->assertRangeApplicable($slice, $upgrade, $spellCode);
        if ($parent !== null && $parent !== '') {
            if ($parent !== $spellCode) {
                throw new GameInvalidException('Game spell upgrade belongs to another spell');
            }

            foreach ($instances as $instance) {
                $domain = $instance->getDomainCode();
                if ($this->covers($slice, $pathCode, $upgrade, $domain)) {
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

            if ($this->covers($slice, $pathCode, $upgrade, $domain)) {
                return;
            }
        }

        throw new GameInvalidException('Game spell upgrade belongs to another path');
    }

    /**
     * Улучшение применимо только к заклинанию со всеми его признаками.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param SpellUpgrade $upgrade Улучшение.
     * @param string $spellCode Заклинание.
     *
     * @return void
     *
     * @throws GameInvalidException Если признак не совпал.
     */
    private function assertSpellKeywords(CharacterRuleSlice $slice, SpellUpgrade $upgrade, string $spellCode): void
    {
        $required = $upgrade->getKeywordCodes();
        if ($required === []) {
            return;
        }

        $rule = $slice->findLive($spellCode);
        $keywords = $rule === null ? [] : $rule->getKeywordCodes();
        if (array_diff($required, $keywords) !== []) {
            throw new GameInvalidException('Game spell upgrade belongs to another school');
        }
    }

    /**
     * Дальность заменяет компонент касания: у заклинания он должен быть.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param SpellUpgrade $upgrade Улучшение.
     * @param string $spellCode Заклинание.
     *
     * @return void
     *
     * @throws GameInvalidException Если заклинание не доставляется касанием.
     */
    private function assertRangeApplicable(CharacterRuleSlice $slice, SpellUpgrade $upgrade, string $spellCode): void
    {
        if ($upgrade->getRange() === null) {
            return;
        }

        $rule = $slice->findLive($spellCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (
            !$spec instanceof AbilitySpellSpec
            || $spec->getSpell()->getTargeting() !== 'none'
            || !$spec->getBase()->getHitResolution() instanceof AttackHit
        ) {
            throw new GameInvalidException('Game spell upgrade range is not applicable');
        }
    }

    /**
     * Улучшение пути применимо к экземпляру на этом пути или на включённом.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $pathCode Путь каста.
     * @param SpellUpgrade $upgrade Улучшение.
     * @param string $domain Домен экземпляра.
     *
     * @return bool true, если применимо.
     */
    private function covers(
        CharacterRuleSlice $slice,
        string $pathCode,
        SpellUpgrade $upgrade,
        string $domain,
    ): bool {
        if ($upgrade->isAnyPath() || $domain === '') {
            return true;
        }

        return $this->paths->reaches($slice, $pathCode, $domain);
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

        if ($upgrade->getResistancePenetrationPerStep() === null && $upgrade->getRange() === null) {
            if ($value !== null && $value !== 0) {
                throw new GameInvalidException('Game spell upgrade value is not allowed');
            }

            return 0;
        }

        $value ??= 1;
        if ($upgrade->getRange() !== null && $value < 1) {
            throw new GameInvalidException('Game spell upgrade range is invalid');
        }

        return $value;
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
        $range = $upgrade->getRange();
        $rangePower = $range === null ? 0 : $value * $range->getPowerPerStep();

        return new GameSpellCastAppliedUpgrade(
            $rule->getCode(),
            $rule->getName(),
            $value,
            $upgrade->getActionPointDelta(),
            $upgrade->getCheckAdvantage() ?? 0,
            $perStep === null ? 0 : $value * $perStep,
            ($perStep === null ? 0 : $value) + $rangePower,
            $upgrade->getChain(),
            $range === null ? 0 : $range->getControlBase(),
            $range !== null,
            $upgrade->isStatePermanence(),
        );
    }
}
