<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Mechanic\Constant\AdvantageSourceLabels;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Преимущества объявленных поддержек строки удара.
 */
final class GameStrikeSupport
{
    /**
     * Поддержки, записанные в строку удара.
     *
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{attacker: list<RollAdvantage>, defender: list<RollAdvantage>} Преимущества.
     */
    public function advantages(array $row): array
    {
        $raw = $row['supporters'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $supports = ['attacker' => [], 'defender' => []];
        if (!is_array($raw)) {
            return $supports;
        }

        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $side = $entry['side'] ?? null;
            $advantage = $entry['advantage'] ?? null;
            $source = $entry['source'] ?? null;
            if (
                ($side !== 'attacker' && $side !== 'defender')
                || !is_int($advantage)
                || $advantage === 0
                || !is_string($source)
                || $source === ''
            ) {
                continue;
            }

            $supports[$side][] = new RollAdvantage($source, $advantage, AdvantageSourceLabels::getLabel($source));
        }

        return $supports;
    }
}
