<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\SpellDeviationBurst;
use Mifrial\Roleplay\Rule\Dto\Spec\SpellDeviationSpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Читает payload механики «Магическое отклонение».
 */
final class SpellDeviationSpecs
{
    /**
     * Разбирает payload карточки.
     *
     * @param array<string|int, mixed> $payload Payload механики.
     *
     * @return SpellDeviationSpec Spec.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    public static function read(array $payload): SpellDeviationSpec
    {
        $data = SpecShape::object($payload, 'data');
        if ($data === null) {
            throw new RuleSpecShapeException('spell_deviation');
        }

        $diceCount = SpecShape::int($data, 'dice_count');
        $dieFaces = SpecShape::int($data, 'die_faces');
        if ($diceCount < 1 || $dieFaces < 2) {
            throw new RuleSpecShapeException('spell_deviation');
        }

        $stateRuleCode = SpecShape::string($data, 'state_rule_code');
        if ($stateRuleCode === '') {
            throw new RuleSpecShapeException('spell_deviation');
        }

        return new SpellDeviationSpec(
            $diceCount,
            $dieFaces,
            SpecShape::int($data, 'neutral_sum'),
            $stateRuleCode,
            self::burst($data),
        );
    }

    /**
     * Взрыв при дубле.
     *
     * @param array<string|int, mixed> $data Данные payload.
     *
     * @return SpellDeviationBurst|null Взрыв или null.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function burst(array $data): ?SpellDeviationBurst
    {
        if (!array_key_exists('burst', $data) || $data['burst'] === null) {
            return null;
        }

        $burst = SpecShape::object($data, 'burst');
        if ($burst === null) {
            throw new RuleSpecShapeException('spell_deviation');
        }

        $damageTypeCode = SpecShape::string($burst, 'damage_type_code');
        $successRating = SpecShape::int($burst, 'success_rating');
        if ($damageTypeCode === '' || $successRating < 1) {
            throw new RuleSpecShapeException('spell_deviation');
        }

        return new SpellDeviationBurst($damageTypeCode, $successRating);
    }
}
