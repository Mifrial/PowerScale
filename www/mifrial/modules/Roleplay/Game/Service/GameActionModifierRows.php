<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionModifierEffect;

/**
 * Строки удара, покрытые областью модификатора.
 */
final class GameActionModifierRows
{
    /**
     * Строки, чей профиль входит в область модификатора.
     *
     * @param list<array<string, mixed>> $rows Строки удара.
     * @param ActionModifierEffect $effect Модификатор.
     *
     * @return list<array<string, mixed>> Покрытые строки.
     */
    public function covered(array $rows, ActionModifierEffect $effect): array
    {
        $covered = [];
        foreach ($rows as $row) {
            $profileType = $row['profile_type'] ?? null;
            if (is_string($profileType) && $effect->appliesTo($profileType, 1)) {
                $covered[] = $row;
            }
        }

        return $covered;
    }
}
