<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\AgeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\AgeSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\AgeStep;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Корень spec возраста: ступени и их эффекты.
 */
final class AgeSpecs
{
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
        $row = SpecShape::requireMap($row, 'ages');

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
        $row = SpecShape::requireMap($row, 'effects');

        return new AgeEffect(
            SpecShape::string($row, 'characteristic_code'),
            SpecShape::int($row, 'delta'),
            SpecShape::optionalString($row, 'scope'),
        );
    }
}
