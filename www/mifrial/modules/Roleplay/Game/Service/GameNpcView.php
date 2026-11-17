<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameNpcRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Карточка NPC: свежий лист, состояния цели и маска секций.
 */
final class GameNpcView
{
    /**
     * Собирает вид.
     *
     * @param GameNpcVisibility $visibility Вырезание секций.
     * @param GameNpcSheetRefresh $refresh Свежий лист.
     * @param GameActiveEffectStates $states Состояния цели из наложенных эффектов.
     *
     * @return void
     */
    public function __construct(
        private readonly GameNpcVisibility $visibility,
        private readonly GameNpcSheetRefresh $refresh,
        private readonly GameActiveEffectStates $states,
    ) {
    }

    /**
     * Карточка. fullSheet оставляет лист целиком.
     *
     * @param GameRecord|null $game Игра или null при создании.
     * @param GameNpcRecord $record Строка.
     * @param bool $fullSheet Ведущий.
     *
     * @return array<string, mixed> JSON.
     */
    public function detail(?GameRecord $game, GameNpcRecord $record, bool $fullSheet): array
    {
        return [
            'npcId' => $record->getId(),
            'gameId' => $record->getGameId(),
            'name' => $record->getName(),
            'version' => $this->visibility->mask(
                $this->withRuntimeStates($game, $record),
                $record->getVisibility(),
                $fullSheet,
            ),
            'actualVersion' => $record->getActualVersion(),
            'visibility' => $record->getVisibility(),
        ];
    }

    /**
     * Свежий лист и состояния из наложенных эффектов.
     *
     * @param GameRecord|null $game Игра или null.
     * @param GameNpcRecord $record Строка.
     *
     * @return array<string, mixed> Версия.
     */
    private function withRuntimeStates(?GameRecord $game, GameNpcRecord $record): array
    {
        $version = $this->refresh->apply($record->getVersion());
        if ($game === null) {
            return $version;
        }

        $sheet = $version['sheet'] ?? null;
        $choices = $version['choices'] ?? null;
        $spaceId = $version['spaceId'] ?? null;
        $revision = $version['rulesRevision'] ?? null;
        if (!is_array($sheet) || !is_array($choices) || !is_int($spaceId) || !is_int($revision)) {
            return $version;
        }

        $version['sheet'] = $this->states->apply(
            $game->getId(),
            $spaceId,
            $revision,
            'npc',
            $record->getId(),
            $sheet,
            $choices,
        );

        return $version;
    }
}
