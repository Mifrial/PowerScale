<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong,MifrialCodingStandard.Metrics.MethodQuality.CognitiveComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Roleplay\Lore\Dto\LoreSectionValues;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Проверка дерева разделов: коды, родители, циклы и глубина.
 */
final class LoreTreeValidator
{
    private const MAX_SECTIONS = 500;

    private const MAX_DEPTH = 5;

    private const ALLOWED_KEYS = ['code', 'name', 'description', 'parentCode', 'sortOrder', 'icon'];

    /**
     * Проверяет дерево.
     *
     * @param array<string|int, mixed> $sections Узлы.
     *
     * @return array<int, LoreSectionValues> Проверенные узлы.
     *
     * @throws LoreInvalidException Если дерево недопустимо.
     */
    public function validate(array $sections): array
    {
        if (!array_is_list($sections)) {
            throw new LoreInvalidException('Lore sections must be a list');
        }

        if (count($sections) > self::MAX_SECTIONS) {
            throw new LoreInvalidException('Lore sections limit is ' . self::MAX_SECTIONS);
        }

        $values = [];
        $parentCodes = [];
        foreach ($sections as $section) {
            $sectionValue = $this->nodeValues($section, $parentCodes);
            $parentCodes[$sectionValue->getCode()] = $sectionValue->getParentCode();
            $values[] = $sectionValue;
        }

        $this->assertTree($parentCodes);

        return $values;
    }

    /**
     * Проверяет и собирает узел.
     *
     * @param mixed $section Узел JSON.
     * @param array<string, string|null> $parentCodes Уже прочитанные коды.
     *
     * @return LoreSectionValues Узел.
     *
     * @throws LoreInvalidException Если узел недопустим.
     */
    private function nodeValues(mixed $section, array $parentCodes): LoreSectionValues
    {
        if (!is_array($section)) {
            throw new LoreInvalidException('Lore section must be an object');
        }

        LorePayload::assertKnownKeys($section, self::ALLOWED_KEYS);
        $code = LoreCode::requireValid(
            trim(LorePayload::requireString($section, 'code')),
            'Lore section code',
        );
        if (array_key_exists($code, $parentCodes)) {
            throw new LoreInvalidException('Lore section code is duplicated: ' . $code);
        }

        $name = trim(LorePayload::requireString($section, 'name'));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new LoreInvalidException('Lore section name must be 1..255 characters');
        }

        $description = LorePayload::optionalString($section, 'description');
        if (mb_strlen($description, 'UTF-8') > 2000) {
            throw new LoreInvalidException('Lore section description is too long');
        }

        $parentCode = $this->parentCode($section);
        $sortOrder = LorePayload::intValue($section, 'sortOrder');
        if ($sortOrder < 0) {
            throw new LoreInvalidException('Lore section sortOrder must be non-negative');
        }

        $icon = LorePayload::optionalString($section, 'icon');
        if (mb_strlen($icon, 'UTF-8') > 64) {
            throw new LoreInvalidException('Lore section icon is too long');
        }

        return new LoreSectionValues($code, $name, $description, $parentCode, $sortOrder, $icon);
    }

    /**
     * Код родителя или null.
     *
     * @param array<string|int, mixed> $section Узел.
     *
     * @return string|null Код или null.
     */
    private function parentCode(array $section): ?string
    {
        $parentCode = LorePayload::stringOrNull($section, 'parentCode');
        $parentCode = $parentCode === null ? null : trim($parentCode);

        return $parentCode === '' ? null : $parentCode;
    }

    /**
     * Проверяет родителей, циклы и глубину.
     *
     * @param array<string, string|null> $parentCodes Код => родитель.
     *
     * @return void
     *
     * @throws LoreInvalidException Если дерево битое.
     */
    private function assertTree(array $parentCodes): void
    {
        foreach (array_keys($parentCodes) as $code) {
            $depth = 1;
            $current = $parentCodes[$code];
            $visited = [$code => true];
            while ($current !== null) {
                if (!array_key_exists($current, $parentCodes)) {
                    throw new LoreInvalidException('Lore section parent is unknown: ' . $current);
                }

                if (isset($visited[$current])) {
                    throw new LoreInvalidException('Lore section tree has a cycle at ' . $current);
                }

                $visited[$current] = true;
                $depth++;
                if ($depth > self::MAX_DEPTH) {
                    throw new LoreInvalidException('Lore section tree is deeper than ' . self::MAX_DEPTH);
                }

                $current = $parentCodes[$current];
            }
        }
    }
}
