<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityGroupSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Выбор ветки способности по полю type.
 */
final class AbilitySpecs
{
    /**
     * Читает документ способности.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return AbilitySpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): AbilitySpec
    {
        $type = SpecShape::optionalString($document, 'type');
        if ($type === null || $type === 'trait' || $type === 'feature' || $type === 'skill') {
            return new AbilityPlainSpec($type, AbilityBases::read($document));
        }

        return self::branch($type, $document);
    }

    /**
     * Ветка с собственным телом.
     *
     * @param string $type Дискриминатор.
     * @param array<string|int, mixed> $document Документ.
     *
     * @return AbilitySpec Spec.
     *
     * @throws RuleSpecShapeException Если type неизвестен.
     */
    private static function branch(string $type, array $document): AbilitySpec
    {
        if ($type === 'group') {
            return new AbilityGroupSpec(SpecShape::int($document, 'selectLimit'));
        }

        $base = AbilityBases::read($document);
        if ($type === 'action') {
            return new AbilityActionSpec($base, ActionComponents::read($document), ProcessSpecs::readOperations($document));
        }

        if ($type === 'process') {
            return new AbilityProcessSpec($base, ProcessSpecs::read($document));
        }

        if ($type === 'spell') {
            return new AbilitySpellSpec(
                $base,
                SpellSpecs::read($document),
                ActionComponents::read($document),
                ProcessSpecs::readOperations($document),
            );
        }

        throw new RuleSpecShapeException('type');
    }
}
