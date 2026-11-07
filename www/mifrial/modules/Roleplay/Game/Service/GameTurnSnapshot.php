<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Снимок листа текущего участника.
 */
final class GameTurnSnapshot
{
    /**
     * Создаёт снимок.
     *
     * @param GameStrikeRules $rules Срез.
     * @param GameNpcRepository $npcs Лист NPC.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Версия и лист участника.
     *
     * @param array{type: string, id: int} $current Текущий.
     *
     * @return array{actualVersion: int, sheet: array<string, mixed>} Снимок.
     *
     * @throws GameNotFoundException Если NPC нет.
     */
    public function of(array $current): array
    {
        if ($current['type'] === 'character') {
            $snapshot = $this->rules->data->characterSnapshot($current['id']);

            return ['actualVersion' => $snapshot['actualVersion'], 'sheet' => $snapshot['sheet']];
        }

        $npc = $this->npcs->getById($current['id']);

        return ['actualVersion' => $npc->getActualVersion(), 'sheet' => $npc->getVersion()['sheet'] ?? []];
    }

    /**
     * Срез правил игры.
     *
     * @param GameRecord $game Игра.
     *
     * @return CharacterRuleSlice Срез.
     */
    public function slice(GameRecord $game): CharacterRuleSlice
    {
        return $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
    }
}
