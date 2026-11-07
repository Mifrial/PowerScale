<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\CharacteristicSpec;

/**
 * Формула характеристики: разбор min/max и коды её операндов.
 */
final class CharacterCharacteristicFormula
{
    /**
     * min(a, b) или max(a, b).
     *
     * @param string $formula Формула.
     *
     * @return array{max: bool, codes: array<int, string>}|null Разбор или null.
     */
    public static function parse(string $formula): ?array
    {
        if (preg_match('/^(min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i', trim($formula), $match) !== 1) {
            return null;
        }

        return ['max' => strtolower($match[1]) === 'max', 'codes' => [trim($match[2]), trim($match[3])]];
    }

    /**
     * Модификатор производной раскладывается на характеристики формулы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код из эффекта.
     *
     * @return array<int, string> Коды, на которые ложится дельта.
     */
    public static function targetCodes(CharacterRuleSlice $slice, string $code): array
    {
        $spec = $slice->findLive($code)?->getSpec();
        $formula = $spec instanceof CharacteristicSpec ? $spec->getFormula() : null;
        $parsed = is_string($formula) ? self::parse($formula) : null;

        return $parsed === null ? [$code] : $parsed['codes'];
    }
}
