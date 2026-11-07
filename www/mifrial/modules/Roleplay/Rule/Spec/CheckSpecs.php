<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\CheckDifficulty;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckOutcome;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Exception\RuleSpecShapeException;

/**
 * Корень spec проверки: сложность, исходы и коды правил.
 */
final class CheckSpecs
{
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
            SpecShape::bool($document, 'push_check'),
            SpecShape::bool($document, 'initiative'),
            self::attached($document),
            self::outcomes($document),
        );
    }

    /**
     * Исходы проверки по рейтингу.
     *
     * @param array<string|int, mixed> $document Документ.
     *
     * @return array<int, CheckOutcome> Список.
     *
     * @throws RuleSpecShapeException Если форма чужая.
     */
    private static function outcomes(array $document): array
    {
        $outcomes = [];
        $seen = [];
        foreach (SpecShape::list($document, 'outcomes') as $row) {
            $row = SpecShape::requireMap($row, 'outcomes');
            $rating = SpecShape::optionalInt($row, 'rating');
            $maxRating = SpecShape::optionalInt($row, 'max_rating');
            $state = SpecShape::optionalString($row, 'state_rule_code');
            if (($rating === null) === ($maxRating === null)) {
                throw new RuleSpecShapeException('outcomes');
            }

            if ($state === '') {
                throw new RuleSpecShapeException('outcomes');
            }

            $key = $rating !== null ? 'rating:' . $rating : 'max:' . $maxRating;
            if (isset($seen[$key])) {
                throw new RuleSpecShapeException('outcomes');
            }

            $seen[$key] = true;
            $outcomes[] = new CheckOutcome($rating, $maxRating, $state);
        }

        return $outcomes;
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
}
