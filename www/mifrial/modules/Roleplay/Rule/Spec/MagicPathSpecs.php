<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathStudyCost;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Магический путь.
 */
final class MagicPathSpecs
{
    /**
     * Читает путь.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return MagicPathSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $document): MagicPathSpec
    {
        return new MagicPathSpec(
            SpecShape::optionalString($document, 'check_code'),
            SpecShape::optionalString($document, 'cast_check_code'),
            SpecShape::optionalString($document, 'power_characteristic_code'),
            SpecShape::optionalString($document, 'control_characteristic_code'),
            self::studyCost($document),
            SpecShape::stringList($document, 'includes_path_codes'),
        );
    }

    /**
     * Цена изучения.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return MagicPathStudyCost|null Цена или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function studyCost(array $document): ?MagicPathStudyCost
    {
        $cost = SpecShape::object($document, 'study_cost');
        if ($cost === null) {
            return null;
        }

        return new MagicPathStudyCost(
            SpecShape::number($cost, 'discount_fraction'),
            SpecShape::optionalInt($cost, 'pair_base_cost'),
        );
    }
}
