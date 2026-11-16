<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterKeywordExperience;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellCharge;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChargeCap;

/**
 * Заряд заклинания: кап, строки состояния на источнике и операции листа.
 *
 * Логика читает только `spell.charge` карточки и улучшение `charge_cap`;
 * коды заклинаний и состояний не зашиты.
 */
final class GameSpellCharge
{
    private readonly CharacterKeywordExperience $experience;

    private readonly GameResourceLimit $limit;

    /**
     * Создаёт расчёт заряда.
     *
     * @return void
     */
    public function __construct()
    {
        $this->experience = new CharacterKeywordExperience();
        $this->limit = new GameResourceLimit();
    }

    /**
     * Заряд карточки.
     *
     * @param AbilitySpellSpec $spell Карточка.
     *
     * @return SpellCharge|null Заряд или null.
     */
    public function spec(AbilitySpellSpec $spell): ?SpellCharge
    {
        return $spell->getSpell()->getCharge();
    }

    /**
     * Заряд живого заклинания по коду.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $spellCode Код заклинания.
     *
     * @return SpellCharge|null Заряд или null.
     */
    public function specOf(CharacterRuleSlice $slice, string $spellCode): ?SpellCharge
    {
        $rule = $slice->findLive($spellCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilitySpellSpec) {
            return null;
        }

        return $this->spec($spec);
    }

    /**
     * Потолок зарядов: `default_cap` или выученное улучшение по опыту.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $choices Выборы кастера.
     * @param array<string, int> $levels Уровни способностей листа.
     * @param string $spellCode Код заклинания.
     * @param SpellCharge $charge Заряд.
     *
     * @return int Потолок.
     */
    public function cap(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        array $levels,
        string $spellCode,
        SpellCharge $charge,
    ): int {
        $upgrade = $this->capUpgrade($slice, $choices, $spellCode);
        if (!$upgrade instanceof SpellChargeCap) {
            return max(0, $charge->getDefaultCap());
        }

        $experience = $this->experience->experience(
            $slice,
            $choices,
            $upgrade->getExperienceKeywordCode(),
            $levels,
        );
        $steps = $upgrade->getSteps();
        usort(
            $steps,
            static fn ($left, $right): int => $right->getMinExperience() <=> $left->getMinExperience(),
        );
        foreach ($steps as $step) {
            if ($experience < $step->getMinExperience()) {
                continue;
            }

            $per = $step->getPerExperience();
            if ($per !== null && $per > 0) {
                return intdiv($experience, $per);
            }

            $cap = $step->getCap();
            if ($cap !== null) {
                return $cap;
            }
        }

        return max(0, $upgrade->getBase());
    }

    /**
     * Текущая сила заряда на источнике.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $stateCode Код состояния.
     * @param string $sourceKey Ключ источника.
     *
     * @return int Сила.
     */
    public function value(array $sheet, string $stateCode, string $sourceKey): int
    {
        $total = 0;
        foreach ($this->rows($sheet, $stateCode, $sourceKey) as $row) {
            $total += max(0, $row['value']);
        }

        return $total;
    }

    /**
     * Операции выдачи заряда.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param SpellCharge $charge Заряд.
     * @param string $sourceKey Ключ источника.
     * @param int $cap Потолок.
     * @param bool $replace Заменить прежний заряд, а не прибавить.
     *
     * @return list<array<string, mixed>> Операции.
     */
    public function grant(array $sheet, SpellCharge $charge, string $sourceKey, int $cap, bool $replace): array
    {
        $stateCode = $charge->getStateCode();
        $rows = $this->rows($sheet, $stateCode, $sourceKey);
        $current = $this->value($sheet, $stateCode, $sourceKey);
        $gained = $replace ? $charge->getGrant() : $current + $charge->getGrant();
        $target = max(0, min($cap, $gained));

        return $this->writeRows($rows, $stateCode, $sourceKey, $current, $target);
    }

    /**
     * Операции траты заряда.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param SpellCharge $charge Заряд.
     * @param string $sourceKey Ключ источника.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если заряда меньше нужного.
     */
    public function spend(array $sheet, SpellCharge $charge, string $sourceKey): array
    {
        $stateCode = $charge->getStateCode();
        $rows = $this->rows($sheet, $stateCode, $sourceKey);
        $current = $this->value($sheet, $stateCode, $sourceKey);
        if ($current < $charge->getAmount()) {
            throw new GameInvalidException('Game spell charge is not enough');
        }

        return $this->writeRows($rows, $stateCode, $sourceKey, $current, $current - $charge->getAmount());
    }

    /**
     * Операции снятия заряда с источника.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $stateCode Код состояния.
     * @param string $sourceKey Ключ источника.
     *
     * @return list<array<string, mixed>> Операции.
     */
    public function remove(array $sheet, string $stateCode, string $sourceKey): array
    {
        return $this->writeRows($this->rows($sheet, $stateCode, $sourceKey), $stateCode, $sourceKey, 0, 0);
    }

    /**
     * Лимит ресурса хода из листа.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param string $resourceCode Код ресурса.
     *
     * @return int Лимит.
     *
     * @throws GameInvalidException Если строки ресурсов битые.
     */
    public function turnLimit(array $sheet, CharacterRuleSlice $slice, string $resourceCode): int
    {
        return $this->limit->of($slice, $sheet, $resourceCode);
    }

    /**
     * Строки состояния на источнике.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $stateCode Код состояния.
     * @param string $sourceKey Ключ источника.
     *
     * @return list<array{id: int, value: int}> Строки.
     */
    private function rows(array $sheet, string $stateCode, string $sourceKey): array
    {
        $rows = [];
        foreach (is_array($sheet['states'] ?? null) ? $sheet['states'] : [] as $row) {
            if (!is_array($row) || ($row['stateRuleCode'] ?? null) !== $stateCode) {
                continue;
            }

            if (($row['boundSourceKey'] ?? null) !== $sourceKey) {
                continue;
            }

            $id = $row['id'] ?? null;
            $value = $row['value'] ?? null;
            if (is_int($id) && $id >= 1 && is_int($value)) {
                $rows[] = ['id' => $id, 'value' => $value];
            }
        }

        return $rows;
    }

    /**
     * Операции, приводящие строки источника к силе.
     *
     * @param list<array{id: int, value: int}> $rows Строки.
     * @param string $stateCode Код состояния.
     * @param string $sourceKey Ключ источника.
     * @param int $current Текущая сила.
     * @param int $target Целевая сила.
     *
     * @return list<array<string, mixed>> Операции.
     */
    private function writeRows(
        array $rows,
        string $stateCode,
        string $sourceKey,
        int $current,
        int $target,
    ): array {
        if ($target <= 0) {
            return array_map(
                static fn (array $row): array => ['kind' => 'removeState', 'id' => $row['id']],
                $rows,
            );
        }

        if ($rows === []) {
            return [
                [
                    'kind' => 'putState',
                    'stateRuleCode' => $stateCode,
                    'value' => $target,
                    'boundSourceKey' => $sourceKey,
                ],
            ];
        }

        $operations = [];
        foreach ($rows as $index => $row) {
            if ($index === 0 && $target !== $current) {
                $operations[] = ['kind' => 'patchState', 'id' => $row['id'], 'value' => $target];

                continue;
            }

            if ($index > 0) {
                $operations[] = ['kind' => 'removeState', 'id' => $row['id']];
            }
        }

        return $operations;
    }

    /**
     * Выученный потолок зарядов этого заклинания.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param CharacterChoices $choices Выборы.
     * @param string $spellCode Код заклинания.
     *
     * @return SpellChargeCap|null Потолок или null.
     */
    private function capUpgrade(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        string $spellCode,
    ): ?SpellChargeCap {
        foreach ($choices->getAbilities() as $ability) {
            if ($ability->getLevel() < 1) {
                continue;
            }

            $rule = $slice->findLive($ability->getRuleCode());
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof AbilityWithBase) {
                continue;
            }

            $base = $spec->getBase();
            $upgrade = $base->getSpellUpgrade();
            if ($upgrade === null || $base->getParentAbilityCode() !== $spellCode) {
                continue;
            }

            $cap = $upgrade->getChargeCap();
            if ($cap instanceof SpellChargeCap) {
                return $cap;
            }
        }

        return null;
    }
}
