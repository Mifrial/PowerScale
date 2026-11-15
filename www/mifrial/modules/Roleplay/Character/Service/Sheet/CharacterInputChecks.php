<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet;

use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterCustomRule;
use Mifrial\Roleplay\Character\Dto\CharacterInventoryChoice;
use Mifrial\Roleplay\Character\Dto\CharacterProblemList;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterItemRecorded;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterRaceAbilityCodes;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemStorage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Проверки входа: имя, раса, ключ экземпляра, tombstone, свои правила.
 */
final class CharacterInputChecks
{
    private readonly CharacterItemRecorded $recorded;

    private readonly CharacterRaceAbilityCodes $raceAbilityCodes;

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
        $this->recorded = new CharacterItemRecorded();
        $this->raceAbilityCodes = new CharacterRaceAbilityCodes();
    }

    /**
     * Пишет отказы входа и ссылок.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     * @param bool $requireRecorded Требовать непустые хранилища (сохранение сборки).
     *
     * @return void
     */
    public function check(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterProblemList $problems,
        bool $requireRecorded = true,
    ): void {
        $this->checkName($choices, $problems);
        $this->checkRace($slice, $choices, $problems);
        $this->checkAbilities($slice, $choices, $problems);
        $this->checkInventory($slice, $choices, $problems, $requireRecorded);
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
        $allowed = array_fill_keys(
            $this->raceAbilityCodes->getCodes($slice, $choices->getRaceCode()),
            true,
        );
        $seen = [];
        foreach ($choices->getAbilities() as $index => $ability) {
            $this->checkAbility($slice, $ability, $index, $seen, $allowed, $problems);
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
     * @param array<string, true> $allowed Ссылки расы.
     * @param CharacterProblemList $problems Накопитель.
     *
     * @return void
     */
    private function checkAbility(
        CharacterRuleSlice $slice,
        CharacterAbilityChoice $ability,
        int $index,
        array $seen,
        array $allowed,
        CharacterProblemList $problems,
    ): void {
        $path = 'abilities.' . $index;
        if (isset($seen[$ability->instanceKey()])) {
            $problems->add('CHARACTER_INSTANCE_KEY', 'Ability instance key is duplicated', 'input', $path);
        }

        if ($slice->hasTombstone($ability->getRuleCode())) {
            $problems->add('CHARACTER_TOMBSTONE', 'Ability is tombstoned', 'reference', $path);

            return;
        }

        $rule = $slice->findLive($ability->getRuleCode());
        if (
            $rule !== null
            && !isset($allowed[$rule->getCode()])
            && in_array('gift', $rule->getKeywordCodes(), true)
        ) {
            $problems->add(
                'CHARACTER_ABILITY_RACE',
                'Gift ability is not available to the race',
                'reference',
                $path . '.ruleCode',
            );
        }
    }

    /**
     * Tombstone предметов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterChoices $choices Выборы.
     * @param CharacterProblemList $problems Накопитель.
     * @param bool $requireRecorded Требовать непустые хранилища.
     *
     * @return void
     */
    private function checkInventory(
        CharacterRuleSlice $slice,
        CharacterChoices $choices,
        CharacterProblemList $problems,
        bool $requireRecorded,
    ): void {
        foreach ($choices->getInventory() as $index => $item) {
            if ($slice->hasTombstone($item->getRuleCode())) {
                $problems->add('CHARACTER_TOMBSTONE', 'Item is tombstoned', 'reference', 'inventory.' . $index);
            }

            $this->checkStorage($slice, $item, $index, $problems, $requireRecorded);
        }
    }

    /**
     * Объём предмета-хранилища: ступень покупки и нижняя граница от мощи.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CharacterInventoryChoice $item Строка инвентаря.
     * @param int $index Индекс строки.
     * @param CharacterProblemList $problems Накопитель.
     * @param bool $requireRecorded Требовать непустые хранилища.
     *
     * @return void
     */
    private function checkStorage(
        CharacterRuleSlice $slice,
        CharacterInventoryChoice $item,
        int $index,
        CharacterProblemList $problems,
        bool $requireRecorded,
    ): void {
        $storage = $this->storageOf($slice, $item->getRuleCode());
        if ($storage === null) {
            return;
        }

        $power = $item->getParameters()[$storage->getPowerParameter()] ?? null;
        if (!$power instanceof DimensionalNumber) {
            $problems->add(
                'CHARACTER_ITEM_VOLUME',
                'Item storage power is missing',
                'reference',
                'inventory.' . $index . '.parameters.' . $storage->getPowerParameter(),
            );

            return;
        }

        $volume = $item->getPayload()[$storage->getVolumeParameter()] ?? null;
        if (!is_int($volume)) {
            $problems->add(
                'CHARACTER_ITEM_VOLUME',
                'Item storage volume is missing',
                'input',
                'inventory.' . $index . '.payload.' . $storage->getVolumeParameter(),
            );

            return;
        }

        $minimum = (new CharacteristicNumber($power->getBase(), $power->getSize()))
            ->modify($storage->getMinVolumePowerDelta())
            ->toInteger();
        $step = $storage->getVolumeIncrement();
        $base = $storage->getVolumeBase();
        $volumePath = 'inventory.' . $index . '.payload.' . $storage->getVolumeParameter();
        if ($volume < $minimum || $volume < $base || ($volume - $base) % $step !== 0) {
            $problems->add(
                'CHARACTER_ITEM_VOLUME',
                'Item storage volume is below the power minimum',
                'input',
                $volumePath,
            );

            return;
        }

        $this->checkRecorded($slice, $storage, $item, $volume, $index, $problems, $requireRecorded);
    }

    /**
     * Записи заклинаний: состав и занятый Объём.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param ItemStorage $storage Блок хранилища.
     * @param CharacterInventoryChoice $item Строка инвентаря.
     * @param int $volume Объём.
     * @param int $index Индекс строки.
     * @param CharacterProblemList $problems Накопитель.
     * @param bool $requireRecorded Требовать непустые хранилища.
     *
     * @return void
     */
    private function checkRecorded(
        CharacterRuleSlice $slice,
        ItemStorage $storage,
        CharacterInventoryChoice $item,
        int $volume,
        int $index,
        CharacterProblemList $problems,
        bool $requireRecorded,
    ): void {
        $parameter = $storage->getRecordedParameter();
        if ($parameter === '') {
            return;
        }

        $prefix = 'inventory.' . $index . '.payload.' . $parameter;
        $raw = $item->getPayload()[$parameter] ?? [];
        if ($requireRecorded && is_array($raw) && $raw === []) {
            $problems->add(
                'CHARACTER_ITEM_RECORDED',
                'Item storage must contain a recorded spell',
                'input',
                $prefix,
            );

            return;
        }

        $occupied = $this->recorded->occupied(
            $slice,
            $raw,
            $storage->getRecordedKeywords(),
        );
        foreach ($occupied['problems'] as $problem) {
            $problems->add($problem['code'], $problem['message'], $problem['stage'], $prefix . '.' . $problem['key']);
        }

        if ($occupied['volume'] > $volume) {
            $problems->add(
                'CHARACTER_ITEM_VOLUME',
                'Item recorded spells do not fit the volume',
                'input',
                $prefix,
            );
        }
    }

    /**
     * Блок хранилища живого предмета.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $ruleCode Код предмета.
     *
     * @return ItemStorage|null Блок или null.
     */
    private function storageOf(CharacterRuleSlice $slice, string $ruleCode): ?ItemStorage
    {
        $rule = $slice->findLive($ruleCode);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();

        return $spec instanceof ItemSpec ? $spec->getStorage() : null;
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
