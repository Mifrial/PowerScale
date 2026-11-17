<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;

/**
 * JSON наложенного длительного эффекта.
 */
final class GameActiveEffectView
{
    /**
     * Собирает JSON строки.
     *
     * @param GameActiveEffectRecord $effect Строка.
     *
     * @return array<string, mixed> JSON.
     */
    public function build(GameActiveEffectRecord $effect): array
    {
        return [
            'id' => (string) $effect->getId(),
            'gameId' => $effect->getGameId(),
            'sourceType' => $effect->getSourceType(),
            'sourceRuleCode' => $effect->getSourceRuleCode(),
            'applierKey' => $effect->getApplierKey(),
            'targetKind' => $effect->getTargetKind(),
            'targetId' => $effect->getTargetId(),
            'targetKey' => $effect->getTargetKey(),
            'durationType' => $effect->getDurationType(),
            'durationUnit' => $effect->getDurationUnit(),
            'turnsTotal' => $effect->getTurnsTotal(),
            'turnsLeft' => $effect->getTurnsLeft(),
            'strength' => $effect->getStrength(),
            'context' => $effect->getContext(),
            'startedRound' => $effect->getStartedRound(),
            'startedParticipant' => $effect->getStartedParticipant(),
            'version' => $effect->getStateVersion(),
        ];
    }
}
