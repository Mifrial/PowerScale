<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Участник проверки в тексте сообщения и спикер.
 */
final class GameCheckSpeakers
{
    /**
     * Создаёт вывод участников.
     *
     * @param ICharacters $characters Персонажи.
     * @param GameNpcRepository $npcs NPC игры.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Токен участника и упоминание концентрации.
     *
     * @param array{type: string, id: int} $participant Участник.
     * @param int $spent Жетоны.
     *
     * @return string Текст.
     */
    public function withConcentration(array $participant, int $spent): string
    {
        if ($spent < 1) {
            return $this->tokenOf($participant);
        }

        return $this->tokenOf($participant) . ', используя концентрацию (' . $spent . '),';
    }

    /**
     * Токен участника.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return string Токен.
     */
    public function tokenOf(array $participant): string
    {
        $kind = $participant['type'] === 'npc' ? 'npc' : 'character';
        $name = $this->nameOf($participant);

        return '[[' . $kind . ':' . $participant['id'] . ',' . $name . ']]';
    }

    /**
     * Спикер сообщения.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string} Спикер.
     */
    public function speakerOf(array $participant): array
    {
        $name = $this->nameOf($participant);
        if ($participant['type'] === 'npc') {
            return ['kind' => 'npc', 'npcId' => $participant['id'], 'npcName' => $name];
        }

        return ['kind' => 'character', 'characterId' => $participant['id'], 'characterName' => $name];
    }

    /**
     * Имя участника.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return string Имя.
     */
    private function nameOf(array $participant): string
    {
        try {
            if ($participant['type'] === 'npc') {
                return $this->npcs->getById($participant['id'])->getName();
            }

            return $this->characters->get($participant['id'])->getName();
        } catch (CharacterNotFoundException | GameNotFoundException) {
            return '';
        }
    }
}
