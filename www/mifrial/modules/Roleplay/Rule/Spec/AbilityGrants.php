<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityCodeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityGrantBlock;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CharacteristicParameterGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CheckAdvantageGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\CheckEfficiencyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ItemGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\KeywordGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MagicPathGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MagicStudyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\MoneyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ProcessDistanceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResistanceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ResourceLimitChangeGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SenseModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SkillStudyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StateModifyGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\Scalar\ScalarFormula;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разбор блоков грантов способности.
 */
final class AbilityGrants
{
    /**
     * Блоки grants.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, AbilityGrantBlock> Блоки.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function blocks(array $document): array
    {
        $blocks = [];
        foreach (SpecShape::list($document, 'grants') as $row) {
            $blocks[] = self::block($row);
        }

        return $blocks;
    }

    /**
     * Один блок уровня.
     *
     * @param mixed $row Строка.
     *
     * @return AbilityGrantBlock Блок.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function block(mixed $row): AbilityGrantBlock
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('grants');
        }

        return new AbilityGrantBlock(SpecShape::int($row, 'level'), self::grants($row));
    }

    /**
     * Гранты блока.
     *
     * @param array<string, mixed> $row Блок.
     *
     * @return array<int, AbilityGrant> Гранты.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function grants(array $row): array
    {
        $grants = [];
        foreach (SpecShape::list($row, 'grants') as $grant) {
            $grants[] = self::one($grant);
        }

        return $grants;
    }

    /**
     * Один грант.
     *
     * @param mixed $row Строка.
     *
     * @return AbilityGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function one(mixed $row): AbilityGrant
    {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuleSpecShapeException('grants');
        }

        $type = SpecShape::string($row, 'type');
        $permanent = SpecShape::bool($row, 'permanent');

        return self::branch($type, $row, $permanent);
    }

    /**
     * Ветка гранта.
     *
     * @param string $type Тип.
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return AbilityGrant Грант.
     *
     * @throws RuleSpecShapeException Если тип чужой.
     */
    private static function branch(string $type, array $row, bool $permanent): AbilityGrant
    {
        return match ($type) {
            'money' => self::money($row, $permanent),
            'characteristic' => self::characteristic($row, $permanent),
            'characteristic_parameter' => self::parameter($row, $permanent),
            'characteristic_modify' => self::modify($row, $permanent),
            'resource' => self::resource($row, $permanent),
            'resource_limit_change' => self::resourceChange($row, $permanent),
            'resistance' => self::resistance($row, $permanent),
            'sense_modify' => self::sense($row, $permanent),
            'state_modify' => self::state($row, $permanent),
            'ability' => self::ability($row, $permanent),
            'keyword' => self::keyword($row, $permanent),
            'item' => self::item($row, $permanent),
            'magic_path' => new MagicPathGrant(SpecShape::string($row, 'path_code'), $permanent),
            'magic_study' => self::magicStudy($row, $permanent),
            'skill_study' => self::skillStudy($row, $permanent),
            'process_distance_multiplier' => self::distance($row, $permanent),
            'check_advantage' => self::advantage($row, $permanent),
            'check_efficiency' => self::efficiency($row, $permanent),
            default => throw new RuleSpecShapeException('type'),
        };
    }

    /**
     * Денежный грант.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return MoneyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function money(array $row, bool $permanent): MoneyGrant
    {
        return new MoneyGrant(
            SpecShape::int($row, 'fixed'),
            SpecShape::int($row, 'percent'),
            SpecShape::string($row, 'apply'),
            $permanent,
        );
    }

    /**
     * Грант характеристики.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return CharacteristicGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function characteristic(array $row, bool $permanent): CharacteristicGrant
    {
        return new CharacteristicGrant(
            SpecShape::string($row, 'characteristic_code'),
            DimensionalNumbers::characteristic($row, 'value'),
            $permanent,
        );
    }

    /**
     * Параметр характеристики.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return CharacteristicParameterGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function parameter(array $row, bool $permanent): CharacteristicParameterGrant
    {
        return new CharacteristicParameterGrant(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::string($row, 'parameter_code'),
            SpecShape::int($row, 'per_unit'),
            $permanent,
        );
    }

    /**
     * Модификатор характеристики.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return CharacteristicModifyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function modify(array $row, bool $permanent): CharacteristicModifyGrant
    {
        return new CharacteristicModifyGrant(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::string($row, 'source_code'),
            SpecShape::stringList($row, 'check_codes'),
            Formulas::scalar($row, 'amount'),
            $permanent,
        );
    }

    /**
     * Грант ресурса.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return ResourceGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resource(array $row, bool $permanent): ResourceGrant
    {
        return new ResourceGrant(SpecShape::string($row, 'resource_code'), self::intOrNumber($row, 'limit'), $permanent);
    }

    /**
     * Изменение лимита ресурса.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return ResourceLimitChangeGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resourceChange(array $row, bool $permanent): ResourceLimitChangeGrant
    {
        return new ResourceLimitChangeGrant(
            SpecShape::string($row, 'resource_code'),
            SpecShape::string($row, 'source_code'),
            Formulas::scalar($row, 'amount'),
            $permanent,
        );
    }

    /**
     * Сопротивление.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return ResistanceGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resistance(array $row, bool $permanent): ResistanceGrant
    {
        return new ResistanceGrant(
            SpecShape::string($row, 'damage_type_code'),
            SpecShape::string($row, 'source_code'),
            self::resistanceValue($row),
            $permanent,
        );
    }

    /**
     * Величина сопротивления.
     *
     * @param array<string, mixed> $row Объект.
     *
     * @return DimensionalNumber|DimensionalFormula|ScalarFormula Величина.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function resistanceValue(array $row): DimensionalNumber|DimensionalFormula|ScalarFormula
    {
        $value = SpecShape::object($row, 'value');
        if ($value === null) {
            return new DimensionalNumber(0, 0);
        }

        if (!array_key_exists('type', $value)) {
            return DimensionalNumbers::pair($value, 'value');
        }

        $type = SpecShape::string($value, 'type');
        if (in_array($type, ['dimensional', 'characteristic', 'actionCharacteristic'], true)) {
            return Formulas::dimensionalNode($value);
        }

        return Formulas::scalarNode($value);
    }

    /**
     * Модификатор чувства.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return SenseModifyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function sense(array $row, bool $permanent): SenseModifyGrant
    {
        return new SenseModifyGrant(
            SpecShape::string($row, 'sense_code'),
            SpecShape::string($row, 'source_code'),
            SpecShape::optionalString($row, 'status'),
            SpecShape::optionalString($row, 'treat_as_good_down_to'),
            Formulas::scalar($row, 'amount'),
            $permanent,
        );
    }

    /**
     * Модификатор состояния.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return StateModifyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function state(array $row, bool $permanent): StateModifyGrant
    {
        return new StateModifyGrant(
            SpecShape::string($row, 'state_code'),
            SpecShape::string($row, 'source_code'),
            Formulas::scalar($row, 'amount'),
            $permanent,
        );
    }

    /**
     * Грант способности.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return AbilityCodeGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function ability(array $row, bool $permanent): AbilityCodeGrant
    {
        return new AbilityCodeGrant(SpecShape::string($row, 'ability_code'), SpecShape::int($row, 'level'), $permanent);
    }

    /**
     * Признак.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return KeywordGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function keyword(array $row, bool $permanent): KeywordGrant
    {
        return new KeywordGrant(SpecShape::string($row, 'keyword_code'), SpecShape::bool($row, 'remove'), $permanent);
    }

    /**
     * Предмет.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return ItemGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function item(array $row, bool $permanent): ItemGrant
    {
        return new ItemGrant(SpecShape::string($row, 'item_code'), SpecShape::int($row, 'quantity'), $permanent);
    }

    /**
     * Изучение магии.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return MagicStudyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function magicStudy(array $row, bool $permanent): MagicStudyGrant
    {
        return new MagicStudyGrant(
            SpecShape::string($row, 'scope'),
            Formulas::scalar($row, 'max_cost'),
            SpecShape::string($row, 'path_code'),
            SpecShape::optionalInt($row, 'max_instances'),
            SpecShape::optionalInt($row, 'paid_cost'),
            $permanent,
        );
    }

    /**
     * Изучение навыка.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return SkillStudyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function skillStudy(array $row, bool $permanent): SkillStudyGrant
    {
        return new SkillStudyGrant(
            SpecShape::stringList($row, 'ability_codes'),
            SpecShape::int($row, 'max_level'),
            SpecShape::int($row, 'paid_cost'),
            SpecShape::optionalInt($row, 'max_instances'),
            $permanent,
        );
    }

    /**
     * Дистанция процесса.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return ProcessDistanceGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function distance(array $row, bool $permanent): ProcessDistanceGrant
    {
        return new ProcessDistanceGrant(SpecShape::string($row, 'ability_code'), SpecShape::int($row, 'multiplier'), $permanent);
    }

    /**
     * Преимущество проверки.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return CheckAdvantageGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function advantage(array $row, bool $permanent): CheckAdvantageGrant
    {
        return new CheckAdvantageGrant(
            SpecShape::int($row, 'amount'),
            SpecShape::stringList($row, 'check_codes'),
            SpecShape::optionalString($row, 'source_code'),
            $permanent,
        );
    }

    /**
     * Эффективность проверки.
     *
     * @param array<string, mixed> $row Объект.
     * @param bool $permanent Постоянный.
     *
     * @return CheckEfficiencyGrant Грант.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function efficiency(array $row, bool $permanent): CheckEfficiencyGrant
    {
        return new CheckEfficiencyGrant(
            SpecShape::int($row, 'amount'),
            SpecShape::stringList($row, 'check_codes'),
            SpecShape::optionalString($row, 'source_code'),
            $permanent,
        );
    }

    /**
     * Целое или размерное число.
     *
     * @param array<string, mixed> $row Объект.
     * @param string $key Ключ.
     *
     * @return int|DimensionalNumber Значение.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function intOrNumber(array $row, string $key): int|DimensionalNumber
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return 0;
        }

        $value = $row[$key];
        if (is_int($value)) {
            return $value;
        }

        if (!is_array($value) || array_is_list($value)) {
            throw new RuleSpecShapeException($key);
        }

        return DimensionalNumbers::pair($value, $key);
    }
}
