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

        return new SupportModifier(array_keys($unique), $advantage);
    }
}
