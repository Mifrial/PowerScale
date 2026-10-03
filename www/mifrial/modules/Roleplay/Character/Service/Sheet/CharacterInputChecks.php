<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterCustomRule;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;

/**
 * Проверки входа: имя, раса, ключ экземпляра, tombstone, свои правила.
 */
final class CharacterInputChecks
{
    /**
     * Принимает разбор active.
     *
     * @param CharacterActiveInput $activeInput Поле active.
     *
     * @return void
     */
    public function __construct(
        private readonly CharacterActiveInput $activeInput,
    ) {
    }

    /**
     * Пишет отказы входа и ссылок.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    public function check(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): void
    {
        $this->checkName($choices, $problems);
        $this->checkRace($slice, $choices, $problems);
        $this->checkAbilities($slice, $choices, $problems);
        $this->checkInventory($slice, $choices, $problems);
        $this->checkCustomRules($choices, $problems);
        $this->checkActive($choices, $problems);
    }

    /**
     * active: нет ключа — true, не bool — отказ.
     *
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkActive(CharacterChoices $choices, CharacterProblemList $problems): void
    {
        $input = $choices->hasActive() ? ['active' => $choices->isActive()] : [];
        $this->activeInput->read($input, $problems);
    }

    /**
     * Имя после trim непустое.
     *
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkName(CharacterChoices $choices, CharacterProblemList $problems): void
    {
        if (trim($choices->getName()) !== '') {
            return;
        }

        $problems->add('CHARACTER_NAME', 'Character name must not be empty', 'input', 'name');
    }

    /**
     * Раса есть, живая и типа race.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkRace(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): void
    {
        $raceCode = $choices->getRaceCode();
        if ($raceCode === null || $raceCode === '') {
            $problems->add('CHARACTER_RACE', 'Race code is required', 'reference', 'raceCode');

            return;
        }

        $this->checkRaceRule($slice, $raceCode, $problems);
    }

    /**
     * Правило расы в срезе.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $raceCode Код.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkRaceRule(CharacterRuleSlice $slice, string $raceCode, CharacterProblemList $problems): void
    {
        if ($slice->hasTombstone($raceCode)) {
            $problems->add('CHARACTER_TOMBSTONE', 'Race is tombstoned', 'reference', 'raceCode');

            return;
        }

        $rule = $slice->findLive($raceCode);
        if ($rule === null || $rule->getType() !== 'race') {
            $problems->add('CHARACTER_RACE', 'Race code must be a live race', 'reference', 'raceCode');
        }
    }

    /**
     * Ключи способностей и tombstone.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkAbilities(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): void
    {
        $seen = [];
        foreach ($choices->getAbilities() as $index => $ability) {
            $this->checkAbility($slice, $ability, $index, $seen, $problems);
            $seen[$ability->instanceKey()] = true;
        }
    }

    /**
     * Одна способность.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterAbilityChoice $ability Выбор.
     * @param int $index Индекс.
     * @param array<string, true> $seen Уже виденные ключи.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkAbility(
        CharacterRuleSlice $slice,
        CharacterAbilityChoice $ability,
        int $index,
        array $seen,
        CharacterProblemList $problems,
    ): void {
        $path = 'abilities.' . $index;
        if (isset($seen[$ability->instanceKey()])) {
            $problems->add('CHARACTER_INSTANCE_KEY', 'Ability instance key is duplicated', 'input', $path);
        }

        if ($slice->hasTombstone($ability->getRuleCode())) {
            $problems->add('CHARACTER_TOMBSTONE', 'Ability is tombstoned', 'reference', $path);
        }
    }

    /**
     * Tombstone предметов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkInventory(CharacterRuleSlice $slice, CharacterChoices $choices, CharacterProblemList $problems): void
    {
        foreach ($choices->getInventory() as $index => $item) {
            if ($slice->hasTombstone($item->getRuleCode())) {
                $problems->add('CHARACTER_TOMBSTONE', 'Item is tombstoned', 'reference', 'inventory.' . $index);
            }
        }
    }

    /**
     * Структура своих правил.
     *
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkCustomRules(CharacterChoices $choices, CharacterProblemList $problems): void
    {
        foreach ($choices->getCustomRules() as $index => $rule) {
            if (!$this->isCustomRule($rule)) {
                $problems->add('CHARACTER_CUSTOM_RULE', 'Custom rule shape is invalid', 'input', 'customRules.' . $index);
            }
        }
    }

    /**
     * Обязательные строки своего правила на месте.
     *
     * @param CharacterCustomRule $rule Запись.
     *
     * @return bool true, если форма верна.
     */
    private function isCustomRule(CharacterCustomRule $rule): bool
    {
        return $rule->getKind() !== ''
            && $rule->getName() !== ''
            && $rule->getDescription() !== ''
            && $rule->getStatus() !== '';
    }
}
