<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\CharacterSheetFormulaContext;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityBase;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MagicStudyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StudyPaths;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;

/**
 * Гейт изучения карточки в рамках пути при сохранении.
 */
final class CharacterPathStudy
{
    private readonly CharacterAbilityGrantReader $grants;

    private readonly CharacterStudyPaths $paths;

    private readonly CharacterAbilityCost $cost;

    private readonly FormulaEvaluations $evaluations;

    private readonly CharacterSheetFormulaContext $contexts;

    /**
     * Собирает проверку.
     *
     * @return void
     */
    public function __construct()
    {
        $this->grants = new CharacterAbilityGrantReader(new CharacterDonorGrants(), new CharacterRaceAbilityCodes());
        $this->paths = new CharacterStudyPaths();
        $this->cost = new CharacterAbilityCost();
        $this->evaluations = new FormulaEvaluations();
        $this->contexts = new CharacterSheetFormulaContext();
    }

    /**
     * Отказы изучения для новых покупок. Уже изученное не отклоняется.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, mixed> $sheet Посчитанный лист.
     * @param array<int, array{code: string, level: int, domainCode: string}> $previous Купленные экземпляры до изменения.
     *
     * @return array<int, CharacterProblem> Отказы.
     */
    public function problems(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $sheet,
        array $previous,
    ): array {
        $levels = $this->levelsOf($sheet);
        $context = $this->contexts->build($sheet);
        $unlocks = $this->unlocks($slice, $choices, $levels);
        $problems = new CharacterProblemList();
        foreach ($choices->getAbilities() as $index => $ability) {
            if ($ability->getLevel() < 1 || $this->boughtBefore($previous, $ability)) {
                continue;
            }

            $this->check($slice, $choices, $ability, $unlocks, $context, $problems, 'abilities.' . $index, $index);
        }

        return $problems->all();
    }

    /**
     * Этот экземпляр уже куплен на том же пути и не ниже уровня.
     *
     * @param array<int, array{code: string, level: int, domainCode: string}> $previous Предыдущие экземпляры.
     * @param CharacterAbilityChoice $ability Текущий выбор.
     *
     * @return bool true, если покупка не новая.
     */
    private function boughtBefore(array $previous, CharacterAbilityChoice $ability): bool
    {
        foreach ($previous as $row) {
            if (
                $row['code'] === $ability->getRuleCode()
                && $row['domainCode'] === $ability->getDomainCode()
                && $row['level'] >= $ability->getLevel()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Проверяет одну карточку.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Все выборы.
     * @param CharacterAbilityChoice $ability Карточка.
     * @param array<int, CharacterDonorGrant> $unlocks Гранты изучения.
     * @param FormulaContext $context Контекст листа.
     * @param CharacterProblemList $problems Накопитель.
     * @param string $path Путь поля.
     * @param int $order Позиция карточки в выборе.
     *
     * @return void
     */
    private function check(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterAbilityChoice $ability,
        array $unlocks,
        FormulaContext $context,
        CharacterProblemList $problems,
        string $path,
        int $order,
    ): void {
        $rule = $slice->findLive($ability->getRuleCode());
        $base = $rule === null || $rule->isSpecBroken() ? null : CharacterAbilityBase::of($rule->getSpec());
        $study = $base?->getStudy();
        if ($base === null || $study === null) {
            return;
        }

        $cardPath = $ability->getDomainCode();
        if (!$this->paths->isPath($slice, $cardPath) || !$study->allows($cardPath)) {
            $problems->add('CHARACTER_STUDY', 'Card cannot be studied on this path', 'requirement', $path);

            return;
        }

        $cost = $this->cost->firstLevel($base, $ability->getParameters());
        if ($cost === null) {
            $problems->add('CHARACTER_STUDY_FORM', 'Card study cost is unsupported', 'requirement', $path);

            return;
        }

        if (!$this->allowed($slice, $choices, $ability, $cost, $order, $unlocks, $context)) {
            $problems->add('CHARACTER_STUDY', 'Study of this card is not opened by the character', 'requirement', $path);
        }
    }

    /**
     * Хотя бы один чужой грант открывает путь, цену и лимит экземпляров.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterAbilityChoice $ability Карточка.
     * @param int $cost Цена первого уровня.
     * @param int $order Позиция карточки.
     * @param array<int, CharacterDonorGrant> $unlocks Гранты.
     * @param FormulaContext $context Контекст.
     *
     * @return bool true, если изучение разрешено.
     */
    private function allowed(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterAbilityChoice $ability,
        int $cost,
        int $order,
        array $unlocks,
        FormulaContext $context,
    ): bool {
        $path = $ability->getDomainCode();
        foreach ($unlocks as $row) {
            if ($row->getDonorCode() === $ability->getRuleCode()) {
                continue;
            }

            $grant = $row->getGrant();
            if (!$grant instanceof MagicStudyGrant) {
                continue;
            }

            $max = $this->maxCost($grant, $context);
            if ($max === null || $cost > $max) {
                continue;
            }

            if (!$this->grantAllowsPath($slice, $grant->getPaths(), $path)) {
                continue;
            }

            if ($grant->getMaxInstances() === null) {
                return true;
            }

            $used = $this->used($slice, $choices, $grant, $max, $context, $order);
            if ($used <= $grant->getMaxInstances()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Число карточек, которые этот грант мог бы открыть.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param MagicStudyGrant $grant Грант.
     * @param int $max Потолок цены.
     * @param FormulaContext $context Контекст.
     * @param int $order Позиция текущей карточки.
     *
     * @return int Экземпляры.
     */
    private function used(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        MagicStudyGrant $grant,
        int $max,
        FormulaContext $context,
        int $order,
    ): int {
        $used = 0;
        $position = 0;
        foreach ($choices->getAbilities() as $other) {
            if ($position > $order) {
                break;
            }

            ++$position;
            if ($other->getLevel() < 1) {
                continue;
            }

            $entry = $this->paths->studied($slice, $other);
            if ($entry === null) {
                continue;
            }

            [$otherBase, $otherPath] = $entry;
            if (!$this->grantAllowsPath($slice, $grant->getPaths(), $otherPath)) {
                continue;
            }

            $otherCost = $this->cost->firstLevel($otherBase, $other->getParameters());
            if ($otherCost !== null && $otherCost <= $max) {
                ++$used;
            }
        }

        return $used;
    }

    /**
     * Грант открывает путь с учётом включений.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param StudyPaths $paths Набор гранта.
     * @param string $path Путь карточки.
     *
     * @return bool true, если открывает.
     */
    private function grantAllowsPath(CharacterRuleSlice $slice, StudyPaths $paths, string $path): bool
    {
        if (!$this->paths->isPath($slice, $path)) {
            return false;
        }

        if ($paths->isAny()) {
            return true;
        }

        foreach ($paths->getCodes() as $owner) {
            if ($this->paths->reaches($slice, $owner, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Активные гранты изучения по эффективным уровням.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param array<string, int> $levels Эффективные уровни.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    private function unlocks(CharacterRuleSlice $slice, CharacterChoices $choices, array $levels): array
    {
        $rows = [];
        foreach ($this->grants->grants($slice, $choices, $levels) as $row) {
            if ($row->getGrant() instanceof MagicStudyGrant) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Потолок цены гранта.
     *
     * @param MagicStudyGrant $grant Грант.
     * @param FormulaContext $context Контекст.
     *
     * @return int|null Потолок или null для битой формулы.
     */
    private function maxCost(MagicStudyGrant $grant, FormulaContext $context): ?int
    {
        try {
            return $this->evaluations->evaluateScalar($grant->getMaxCost(), $context);
        } catch (RuleInvalidException) {
            return null;
        }
    }

    /**
     * Уровни из документа sheet.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, int> Код → уровень.
     */
    private function levelsOf(array $sheet): array
    {
        $rows = $sheet['abilityLevels'] ?? null;
        if (!is_array($rows)) {
            return [];
        }

        $levels = [];
        foreach ($rows as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        return $levels;
    }
}
