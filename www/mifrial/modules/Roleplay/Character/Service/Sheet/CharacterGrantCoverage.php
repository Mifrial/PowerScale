<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterDonorGrant;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityCodeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MagicStudyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MoneyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SkillStudyGrant;

/**
 * Покрытие пути, skill_study, денежный грант и однозначный грант ability.
 */
final class CharacterGrantCoverage
{
    /**
     * Принимает читатель spec.
     *
     * @param CharacterDonorGrants $donorGrants Гранты способностей.
     * @param CharacterSpecReader $specReader Коды вложенных путей.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterDonorGrants $donorGrants,
        private readonly CharacterSpecReader $specReader,
    ) {
    }

    /**
     * Проверяет гранты и возвращает покрытые пути по коду способности.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return array<string, array<int, string>> Ключ экземпляра → покрытые пути.
     */
    public function check(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): array
    {
        $donors = $this->donorGrants($slice, $choices);
        $this->checkMoney($donors, $problems);
        $covered = [];
        foreach ($choices->getAbilities() as $index => $ability) {
            $covered[$ability->instanceKey()] = $this->coveredPaths($slice, $donors, $ability);
            $this->checkSkill($donors, $ability, $index, $problems);
            $this->checkAbilityGrant($donors, $ability, $index, $problems);
        }

        return $covered;
    }

    /**
     * Гранты выбранных способностей.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     *
     * @return array<int, CharacterDonorGrant> Гранты.
     */
    private function donorGrants(CharacterRuleSlice $slice, CharacterChoices $choices): array
    {
        $rows = [];
        $seenDonors = [];
        foreach ($choices->getAbilities() as $ability) {
            $donor = $ability->getRuleCode();
            if (isset($seenDonors[$donor])) {
                continue;
            }

            $seenDonors[$donor] = true;
            $rule = $slice->findLive($donor);
            if ($rule === null) {
                continue;
            }

            $rows = array_merge($rows, $this->donorGrants->getAll($rule, $donor));
        }

        return $rows;
    }

    /**
     * Несколько денежных грантов без выбора одного.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkMoney(array $donors, CharacterProblemList $problems): void
    {
        if ($this->countMoney($donors) > 1) {
            $problems->add('CHARACTER_GRANT', 'Money grant is ambiguous', 'requirement', 'grants.money');
        }
    }

    /**
     * Несколько skill_study на код без указателя донора.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param int $index Индекс.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkSkill(array $donors, CharacterAbilityChoice $ability, int $index, CharacterProblemList $problems): void
    {
        if ($this->pointerMisses($this->skillDonors($donors, $ability), $ability->getGrantedByRuleCode())) {
            $problems->add('CHARACTER_GRANT', 'Skill study grant is ambiguous', 'requirement', 'abilities.' . $index);
        }
    }

    /**
     * Несколько грантов ability на код без указателя.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param int $index Индекс.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkAbilityGrant(
        array $donors,
        CharacterAbilityChoice $ability,
        int $index,
        CharacterProblemList $problems,
    ): void {
        if ($this->pointerMisses($this->abilityDonors($donors, $ability), $ability->getGrantedByRuleCode())) {
            $problems->add('CHARACTER_GRANT', 'Ability grant is ambiguous', 'requirement', 'abilities.' . $index);
        }
    }

    /**
     * Пути экземпляра, до которых доходит непустой path_code.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterAbilityChoice $ability Выбор.
     *
     * @return array<int, string> Пути.
     */
    private function coveredPaths(CharacterRuleSlice $slice, array $donors, CharacterAbilityChoice $ability): array
    {
        $domainCode = $ability->getDomainCode();
        if ($domainCode === '') {
            return [];
        }

        $covered = [];
        foreach ($donors as $row) {
            $pathCode = $this->studyPath($row);
            if ($pathCode !== '' && $this->reaches($slice, $pathCode, $domainCode)) {
                $covered[] = $domainCode;
            }
        }

        return array_values(array_unique($covered));
    }

    /**
     * path_code гранта magic_study или пустая строка.
     *
     * @param CharacterDonorGrant $row Грант донора.
     *
     * @return string Код или пусто.
     */
    private function studyPath(CharacterDonorGrant $row): string
    {
        $grant = $row->getGrant();
        if (!$grant instanceof MagicStudyGrant) {
            return '';
        }

        return $grant->getPathCode();
    }

    /**
     * Указатель не выбирает ровно одного донора, когда их несколько или он чужой.
     * Один донор без указателя остаётся однозначным.
     *
     * @param array<int, string> $donors Коды доноров.
     * @param string|null $grantedBy Указатель или null.
     *
     * @return bool true, если грант не разрешён.
     */
    private function pointerMisses(array $donors, ?string $grantedBy): bool
    {
        if ($grantedBy === null || $donors === []) {
            return count($donors) > 1;
        }

        $hits = 0;
        foreach ($donors as $donor) {
            if ($donor === $grantedBy) {
                $hits++;
            }
        }

        return $hits !== 1;
    }

    /**
     * Доноры гранта ability на код выбора.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterAbilityChoice $ability Выбор.
     *
     * @return array<int, string> Коды доноров.
     */
    private function abilityDonors(array $donors, CharacterAbilityChoice $ability): array
    {
        $matched = [];
        foreach ($donors as $row) {
            $grant = $row->getGrant();
            if ($grant instanceof AbilityCodeGrant && $grant->getAbilityCode() === $ability->getRuleCode()) {
                $matched[] = $row->getDonorCode();
            }
        }

        return $matched;
    }

    /**
     * Доноры skill_study, в чей список входит код.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     * @param CharacterAbilityChoice $ability Выбор.
     *
     * @return array<int, string> Коды доноров.
     */
    private function skillDonors(array $donors, CharacterAbilityChoice $ability): array
    {
        $matched = [];
        foreach ($donors as $row) {
            $grant = $row->getGrant();
            if ($grant instanceof SkillStudyGrant && in_array($ability->getRuleCode(), $grant->getAbilityCodes(), true)) {
                $matched[] = $row->getDonorCode();
            }
        }

        return $matched;
    }

    /**
     * Число денежных грантов.
     *
     * @param array<int, CharacterDonorGrant> $donors Гранты.
     *
     * @return int Число.
     */
    private function countMoney(array $donors): int
    {
        $count = 0;
        foreach ($donors as $row) {
            if ($row->getGrant() instanceof MoneyGrant) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * path_code или транзитивный includes_path_codes доходит до цели.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $start Код пути гранта.
     * @param string $target Код пути экземпляра.
     *
     * @return bool true, если покрыт.
     */
    private function reaches(CharacterRuleSlice $slice, string $start, string $target): bool
    {
        $queue = [$start];
        $seen = [];
        while ($queue !== []) {
            $code = array_shift($queue);
            if (!is_string($code) || isset($seen[$code])) {
                continue;
            }

            if ($code === $target) {
                return true;
            }

            $seen[$code] = true;
            $queue = array_merge($queue, $this->included($slice, $code));
        }

        return false;
    }

    /**
     * Прямые includes пути.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код пути.
     *
     * @return array<int, string> Коды.
     */
    private function included(CharacterRuleSlice $slice, string $code): array
    {
        $rule = $slice->findLive($code);
        if ($rule === null) {
            return [];
        }

        return $this->specReader->getIncludedPathCodes($rule);
    }
}
