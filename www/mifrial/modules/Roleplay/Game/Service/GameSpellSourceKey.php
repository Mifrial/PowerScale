<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Ключ источника сотворения: item / grant / characteristic.
 */
final class GameSpellSourceKey
{
    /**
     * Ключ по нормализованному источнику каста.
     *
     * @param array<string, mixed> $source Источник.
     *
     * @return string Ключ.
     *
     * @throws GameInvalidException Если вид источника чужой.
     */
    public static function of(array $source): string
    {
        return match ($source['kind'] ?? null) {
            'item' => 'inventory:' . (int) ($source['inventoryId'] ?? 0),
            'grant' => 'grant:' . (string) ($source['abilityCode'] ?? ''),
            'characteristic' => 'characteristic:' . (string) ($source['code'] ?? ''),
            default => throw new GameInvalidException('Game spell power source is invalid'),
        };
    }
}
