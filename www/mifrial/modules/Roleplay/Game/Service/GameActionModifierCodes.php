<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Коды объявленных модификаторов действия: строка удара и общая форма.
 */
final class GameActionModifierCodes
{
    /**
     * Коды из строки удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return list<string> Коды.
     */
    public static function ofRow(array $row): array
    {
        $raw = $row['action_modifiers'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return self::listed($raw);
    }

    /**
     * Общий набор кодов строк удара.
     *
     * @param list<array<string, mixed>> $rows Строки удара.
     *
     * @return list<string> Коды.
     *
     * @throws GameInvalidException Если наборы строк различаются.
     */
    public static function sharedRows(array $rows): array
    {
        $shared = null;
        foreach ($rows as $row) {
            $codes = self::ofRow($row);
            if ($shared === null) {
                $shared = $codes;
                continue;
            }

            if ($codes !== $shared) {
                throw new GameInvalidException('Game action modifiers differ across strikes');
            }
        }

        return $shared ?? [];
    }

    /**
     * Общий набор кодов объявленных строк выбора.
     *
     * @param list<array<string, mixed>> $strikes Строки выбора.
     *
     * @return list<string> Коды.
     *
     * @throws GameInvalidException Если наборы строк различаются.
     */
    public static function sharedDeclared(array $strikes): array
    {
        $shared = null;
        foreach ($strikes as $strike) {
            $codes = self::listed($strike['modifiers'] ?? null);
            if ($shared === null) {
                $shared = $codes;
                continue;
            }

            if ($codes !== $shared) {
                throw new GameInvalidException('Game action modifiers differ across strikes');
            }
        }

        return $shared ?? [];
    }

    /**
     * Список кодов из поля строки.
     *
     * @param mixed $raw Сырое поле.
     *
     * @return list<string> Коды.
     */
    private static function listed(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $codes = [];
        foreach ($raw as $code) {
            if (is_string($code) && $code !== '' && !in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
