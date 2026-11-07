<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Choices атакующего NPC широкого удара.
 */
final class GameWideChoices
{
    /**
     * Создаёт доступ к choices NPC.
     *
     * @param GameNpcRepository $npcs NPC.
     *
     * @return void
     */
    public function __construct(private readonly GameNpcRepository $npcs)
    {
    }

    /**
     * Снимок choices атакующего NPC.
     *
     * @param int $gameId Игра.
     * @param int $npcId NPC.
     *
     * @return array<string, mixed> Документ choices.
     *
     * @throws GameInvalidException Если строка чужая или документа нет.
     */
    public function of(int $gameId, int $npcId): array
    {
        $npc = $this->npcs->getById($npcId);
        $choices = $npc->getGameId() === $gameId ? ($npc->getVersion()['choices'] ?? null) : null;
        if (!is_array($choices)) {
            throw new GameInvalidException('Game wide strike npc choices are missing');
        }

        return $choices;
    }
}
