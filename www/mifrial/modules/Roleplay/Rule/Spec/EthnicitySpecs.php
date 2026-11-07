<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\EthnicitySpec;
use Mifrial\Roleplay\Rule\Dto\Spec\EthnicityUsage;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Корень spec народа: роль, предки и пары языка.
 */
final class EthnicitySpecs
{
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
            $row = SpecShape::requireMap($row, 'usages');

            $usages[] = new EthnicityUsage(SpecShape::string($row, 'language_code'), SpecShape::stringList($row, 'script_codes'));
        }

        return $usages;
    }
}
