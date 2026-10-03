<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\AgeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\AgeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\AgeStep;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicBaseFrom;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckDifficulty;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\EthnicitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\EthnicityUsage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\LanguageSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\PoisonSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceAdjustment;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceLimit;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ScriptSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SenseSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\WeaponFamilySpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;

/**
 * Корни spec, которые читает RuleSpecs напрямую.
 */
final class RootSpecs
{
    /**
     * Яд.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return PoisonSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function poison(array $document): PoisonSpec
    {
        return new PoisonSpec(
            SpecShape::optionalString($document, 'icon_code'),
            SpecShape::string($document, 'damage_type_code'),
            DimensionalNumbers::optional($document, 'default_strength'),
        );
    }

    /**
     * Чувство.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return SenseSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function sense(array $document): SenseSpec
    {
        return new SenseSpec(
            SpecShape::string($document, 'status'),
            DimensionalNumbers::required($document, 'radius'),
        );
    }

    /**
     * Тип урона.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return DamageTypeSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function damageType(array $document): DamageTypeSpec
    {
        $forms = SpecShape::object($document, 'forms') ?? [];

        return new DamageTypeSpec(
            SpecShape::string($forms, 'genitive'),
            SpecShape::string($forms, 'dative'),
            SpecShape::bool($document, 'defense_ignored'),
            SpecShape::bool($document, 'modifies_spell_difficulty'),
            SpecShape::optionalInt($document, 'max_success_rating'),
        );
    }

    /**
     * Ресурс.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ResourceSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function resource(array $document): ResourceSpec
    {
        return new ResourceSpec(
            SpecShape::bool($document, 'is_dimensional'),
            SpecShape::bool($document, 'auto_add'),
            SpecShape::bool($document, 'check_token'),
            self::resourceLimit($document),
        );
    }

    /**
     * Характеристика.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CharacteristicSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function characteristic(array $document): CharacteristicSpec
    {
        return new CharacteristicSpec(
            SpecShape::optionalString($document, 'formula'),
            SpecShape::optionalString($document, 'group'),
            self::automatic($document),
            SpecShape::bool($document, 'damage_endurance'),
            SpecShape::bool($document, 'willpower'),
            SpecShape::bool($document, 'concentration_threshold'),
            SpecShape::bool($document, 'concentration_token'),
            SpecShape::bool($document, 'unstable_roll'),
            SpecShape::bool($document, 'initiative'),
            SpecShape::bool($document, 'dodge_soak'),
            self::baseFrom($document),
            self::weaponMastery($document),
        );
    }

    /**
     * Возраст.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return AgeSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function age(array $document): AgeSpec
    {
        $steps = [];
        foreach (SpecShape::list($document, 'ages') as $row) {
            $steps[] = self::ageStep($row);
        }

        return new AgeSpec($steps);
    }

    /**
     * Проверка.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CheckSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function check(array $document): CheckSpec
    {
        $launch = array_key_exists('dialog_launch', $document) ? SpecShape::bool($document, 'dialog_launch') : true;

        return new CheckSpec(
            SpecShape::optionalString($document, 'parent_check_code'),
            SpecShape::optionalString($document, 'characteristic_code'),
            SpecShape::bool($document, 'allow_characteristic_override'),
            SpecShape::optionalInt($document, 'default_efficiency'),
            self::difficulty($document),
            SpecShape::string($document, 'allowed_modes'),
            $launch,
            SpecShape::bool($document, 'ordinary_root'),
            SpecShape::bool($document, 'concentration_token'),
            SpecShape::bool($document, 'willpower'),
            SpecShape::bool($document, 'unstable_check'),
            SpecShape::bool($document, 'hit_check'),
            self::attached($document),
        );
    }

    /**
     * Состояние.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return StateSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function state(array $document): StateSpec
    {
        return new StateSpec(
            SpecShape::optionalString($document, 'icon_code'),
            SpecShape::string($document, 'value_type'),
            SpecShape::string($document, 'aggregation'),
            SpecShape::stringList($document, 'action_codes'),
            SpecShape::optionalString($document, 'check_code'),
            SpecShape::bool($document, 'damage_remainder'),
            SpecShape::bool($document, 'damage_exhaustion'),
            SpecShape::bool($document, 'blood_loss'),
            SpecShape::bool($document, 'decline_weakness'),
            SpecShape::bool($document, 'decline_disabled'),
            SpecShape::bool($document, 'decline_unconscious'),
            SpecShape::bool($document, 'maim'),
            SpecShape::bool($document, 'burning'),
            SpecShape::bool($document, 'poisoning'),
            SpecShape::bool($document, 'lying'),
            SpecShape::bool($document, 'unstable'),
            SpecShape::bool($document, 'magic_deviation'),
            StateEffects::list($document),
        );
    }

    /**
     * Народ.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return EthnicitySpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function ethnicity(array $document): EthnicitySpec
    {
        return new EthnicitySpec(
            SpecShape::string($document, 'role'),
            SpecShape::optionalString($document, 'parent_code'),
            SpecShape::stringList($document, 'race_codes'),
            SpecShape::stringList($document, 'language_codes'),
            self::usages($document),
        );
    }

    /**
     * Язык.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return LanguageSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function language(array $document): LanguageSpec
    {
        return new LanguageSpec(
            SpecShape::string($document, 'role'),
            SpecShape::optionalString($document, 'parent_code'),
            SpecShape::stringList($document, 'script_codes'),
        );
    }

    /**
     * Письменность.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ScriptSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function script(array $document): ScriptSpec
    {
        return new ScriptSpec(SpecShape::string($document, 'kind'));
    }

    /**
     * Семья оружия.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return WeaponFamilySpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function weaponFamily(array $document): WeaponFamilySpec
    {
        return new WeaponFamilySpec(SpecShape::intList($document, 'costs'));
    }

    /**
     * Тип модификатора.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ItemModifierTypeSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function modifierType(array $document): ItemModifierTypeSpec
    {
        return new ItemModifierTypeSpec(SpecShape::bool($document, 'exclusive'));
    }

    /**
     * Автополучение: нет ключа — false, true — флаг, объект — база.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return bool|CharacteristicNumber Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function automatic(array $document): bool|CharacteristicNumber
    {
        if (!array_key_exists('automatic', $document) || $document['automatic'] === null) {
            return false;
        }

        $value = $document['automatic'];
        if (is_bool($value)) {
            return $value;
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new RuleSpecShapeException('automatic');
        }

        return DimensionalNumbers::characteristic($value, 'value');
    }

    /**
     * Лимит ресурса. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return ResourceLimit|null Лимит.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resourceLimit(array $document): ?ResourceLimit
    {
        $limit = SpecShape::object($document, 'limit');
        if ($limit === null) {
            return null;
        }

        $adjustments = [];
        foreach (SpecShape::list($limit, 'adjustments') as $row) {
            $adjustments[] = self::adjustment($row);
        }

        return new ResourceLimit(AbilityGrants::intOrNumber($limit, 'base'), $adjustments);
    }

    /**
     * Одна поправка.
     *
     * @param mixed $row Строка.
     *
     * @return ResourceAdjustment Поправка.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function adjustment(mixed $row): ResourceAdjustment
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('adjustments');
        }

        return new ResourceAdjustment(Formulas::scalar($row, 'value'), SpecShape::string($row, 'source_code'));
    }

    /**
     * Ступень возраста.
     *
     * @param mixed $row Строка.
     *
     * @return AgeStep Ступень.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ageStep(mixed $row): AgeStep
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('ages');
        }

        $effects = [];
        foreach (SpecShape::list($row, 'effects') as $effect) {
            $effects[] = self::ageEffect($effect);
        }

        return new AgeStep(SpecShape::string($row, 'name'), SpecShape::int($row, 'ol'), SpecShape::int($row, 'featureLimit'), $effects);
    }

    /**
     * Эффект возраста.
     *
     * @param mixed $row Строка.
     *
     * @return AgeEffect Эффект.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ageEffect(mixed $row): AgeEffect
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('effects');
        }

        return new AgeEffect(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::int($row, 'delta'),
            SpecShape::optionalString($row, 'scope'),
        );
    }

    /**
     * Сложность. Нет ключа — none.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CheckDifficulty Сложность.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function difficulty(array $document): CheckDifficulty
    {
        $value = SpecShape::object($document, 'difficulty_input');
        if ($value === null) {
            return new CheckDifficulty('none', '');
        }

        return new CheckDifficulty(SpecShape::string($value, 'kind'), SpecShape::string($value, 'state_code'));
    }

    /**
     * Коды правил на броске. Нет ключа — null.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, string>|null Список или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function attached(array $document): ?array
    {
        if (!array_key_exists('attached_rule_codes', $document) || $document['attached_rule_codes'] === null) {
            return null;
        }

        return SpecShape::stringList($document, 'attached_rule_codes');
    }

    /**
     * Пары языка.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, EthnicityUsage> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function usages(array $document): array
    {
        $usages = [];
        foreach (SpecShape::list($document, 'usages') as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new RuleSpecShapeException('usages');
            }

            $usages[] = new EthnicityUsage(SpecShape::string($row, 'language_code'), SpecShape::stringList($row, 'script_codes'));
        }

        return $usages;
    }

    /**
     * База из другой характеристики.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return CharacteristicBaseFrom|null Ссылка или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function baseFrom(array $document): ?CharacteristicBaseFrom
    {
        $row = SpecShape::object($document, 'base_from');
        if ($row === null) {
            return null;
        }

        return new CharacteristicBaseFrom(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::stringList($row, 'source_codes'),
        );
    }

    /**
     * Профили мастерства.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, string> Профили.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function weaponMastery(array $document): array
    {
        $row = SpecShape::object($document, 'weapon_mastery');

        return $row === null ? [] : SpecShape::stringList($row, 'profiles');
    }
}
