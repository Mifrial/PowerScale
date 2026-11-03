<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SupportModifier;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Узел support: кому карточка-реакция может дать преимущество.
 */
final class SupportSpecs
{
    /**
     * Кто может быть поддержан.
     *
     * @var array<int, string>
     */
    private const SIDES = ['attacker', 'defender'];

    /**
     * Профили оружия.
     *
     * @var array<int, string>
     */
    private const PROFILES = ['strike', 'throw', 'shoot'];

    /**
     * Читает узел support или null, если его нет.
     *
     * @param array<string|int, mixed> $document Документ способности.
     *
     * @return SupportModifier|null Поддержка или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): ?SupportModifier
    {
        $row = SpecShape::object($document, 'support');
        if ($row === null) {
            return null;
        }

        $sides = SpecShape::stringList($row, 'sides');
        if ($sides === []) {
            throw new RuleSpecShapeException('support.sides');
        }

        $unique = [];
        foreach ($sides as $side) {
            if (!in_array($side, self::SIDES, true)) {
                throw new RuleSpecShapeException('support.sides');
            }

            $unique[$side] = true;
        }

        $advantage = SpecShape::int($row, 'advantage');
        if ($advantage < 0) {
            throw new RuleSpecShapeException('support.advantage');
        }

        $profile = SpecShape::optionalString($row, 'profile');
        if ($profile !== null && !in_array($profile, self::PROFILES, true)) {
            throw new RuleSpecShapeException('support.profile');
        }

        $minMastery = DimensionalNumbers::optional($row, 'min_mastery');

        $source = SpecShape::optionalString($row, 'source');
        if ($source !== null && trim($source) === '') {
            throw new RuleSpecShapeException('support.source');
        }

        return new SupportModifier(
            array_keys($unique),
            $advantage,
            $profile,
            $minMastery,
            $source ?? 'circumstances',
        );
    }
}
