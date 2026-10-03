<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\AgeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\DamageTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\EthnicitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemModifierTypeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\RaceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\LanguageSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\PoisonSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Race\SpeciesSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\RuleSpecRead;
use Mifrial\Roleplay\Rule\Dto\Spec\ScriptSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\SenseSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\WeaponFamilySpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Разбор spec по типу правила. Неизвестный тип и типы без spec — не отказ.
 */
final class RuleSpecs
{
    /**
     * Читает документ версии.
     *
     * @param string $ruleType Тип правила.
     * @param array<string|int, mixed> $document Сырой JSON.
     *
     * @return RuleSpecRead Итог.
     */
    public static function read(string $ruleType, array $document): RuleSpecRead
    {
        if (!self::hasContract($ruleType)) {
            return new RuleSpecRead(null, false);
        }

        try {
            return new RuleSpecRead(self::parse($ruleType, $document), false);
        } catch (RuleSpecShapeException) {
            return new RuleSpecRead(null, true);
        }
    }

    /**
     * У типа есть контракт spec.
     *
     * @param string $ruleType Тип.
     *
     * @return bool true, если тип разбирается.
     */
    private static function hasContract(string $ruleType): bool
    {
        return !in_array($ruleType, ['simple', 'points', 'source'], true) && isset(self::parsers()[$ruleType]);
    }

    /**
     * Контракт известного типа.
     *
     * @param string $ruleType Тип.
     * @param array<string|int, mixed> $document Документ.
     *
     * @return RuleSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function parse(string $ruleType, array $document): RuleSpec
    {
        $parser = self::parsers()[$ruleType];

        return $parser($document);
    }

    /**
     * Разборщики корней RuleSpec.
     *
     * @return array<string, callable(array<string|int, mixed>): RuleSpec> Карта.
     */
    private static function parsers(): array
    {
        return [
            'race' => RaceSpecs::race(...),
            'species' => RaceSpecs::species(...),
            'ability' => AbilitySpecs::read(...),
            'item' => ItemSpecs::read(...),
            'magic_path' => MagicPathSpecs::read(...),
            'age' => RootSpecs::age(...),
            'script' => RootSpecs::script(...),
            'weapon_family' => RootSpecs::weaponFamily(...),
            'language' => RootSpecs::language(...),
            'ethnicity' => RootSpecs::ethnicity(...),
            'characteristic' => RootSpecs::characteristic(...),
            'resource' => RootSpecs::resource(...),
            'state' => RootSpecs::state(...),
            'check' => RootSpecs::check(...),
            'damage_type' => RootSpecs::damageType(...),
            'sense' => RootSpecs::sense(...),
            'poison' => RootSpecs::poison(...),
            'item_modifier' => ItemModifiers::read(...),
            'item_modifier_type' => RootSpecs::modifierType(...),
        ];
    }
}
