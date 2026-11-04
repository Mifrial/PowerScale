<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Сообщения проверок: объявление, бросок и исход.
 */
final class GameCheckChat
{
    /**
     * Создаёт билдеры.
     *
     * @param IChats $chats Чат игры.
     * @param ICharacters $characters Персонажи.
     * @param GameNpcRepository $npcs NPC игры.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
        private readonly ICharacters $characters,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Предложение проверки.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{type: string, id: int} $initiator Инициатор.
     * @param array{type: string, id: int} $opponent Цель.
     * @param string $ruleCode Карточка проверки.
     *
     * @return void
     */
    public function postProposal(
        GameRecord $game,
        int $actorUserId,
        array $initiator,
        array $opponent,
        string $ruleCode,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($initiator) . ' предлагает проверку [[rule:' . $ruleCode . ']] против '
                . $this->tokenOf($opponent) . '.',
            [],
            $this->speakerOf($initiator),
        );
    }

    /**
     * Отказ от предложения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{type: string, id: int} $side Отказавшаяся сторона.
     * @param string $ruleCode Карточка проверки.
     *
     * @return void
     */
    public function postDeclined(GameRecord $game, int $actorUserId, array $side, string $ruleCode): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($side) . ' отклоняет проверку [[rule:' . $ruleCode . ']].',
            [],
            $this->speakerOf($side),
        );
    }

    /**
     * Отмена предложения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{type: string, id: int} $side Отменившая сторона.
     * @param string $ruleCode Карточка проверки.
     *
     * @return void
     */
    public function postCancelled(GameRecord $game, int $actorUserId, array $side, string $ruleCode): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($side) . ' отменяет проверку [[rule:' . $ruleCode . ']].',
            [],
            $this->speakerOf($side),
        );
    }

    /**
     * Итог совместной проверки: бросок каждой стороны.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array<string, mixed> $proposal Предложение.
     * @param string $ruleCode Карточка проверки.
     * @param array<string, array<string, mixed>> $rolls Броски сторон.
     *
     * @return void
     */
    public function postResolution(
        GameRecord $game,
        int $actorUserId,
        array $proposal,
        string $ruleCode,
        array $rolls,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        foreach (['initiator', 'opponent'] as $side) {
            $party = $proposal[$side] ?? null;
            $roll = $rolls[$side] ?? null;
            if (!is_array($party) || !is_array($roll)) {
                continue;
            }

            $this->send(
                $chatId,
                $actorUserId,
                $this->tokenOf($party) . ' бросает проверку [[rule:' . $ruleCode . ']].',
                [['type' => 'roll', 'payload' => $roll]],
                $this->speakerOf($party),
            );
        }
    }

    /**
     * Соло-проверка: объявление и бросок.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{type: string, id: int} $participant Участник.
     * @param string $ruleCode Карточка проверки.
     * @param array<string, mixed> $thrown Бросок.
     *
     * @return void
     */
    public function postSolo(
        GameRecord $game,
        int $actorUserId,
        array $participant,
        string $ruleCode,
        array $thrown,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $speaker = $this->speakerOf($participant);
        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($participant) . ' совершает проверку [[rule:' . $ruleCode . ']].',
            [],
            $speaker,
        );
        $this->send($chatId, $actorUserId, '', [['type' => 'roll', 'payload' => $thrown]], $speaker);
    }

    /**
     * Токен участника.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return string Токен.
     */
    private function tokenOf(array $participant): string
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
    private function speakerOf(array $participant): array
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

    /**
     * Отправляет сообщение со спикером.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string} $speaker Спикер.
     *
     * @return void
     */
    private function send(int $chatId, int $actorUserId, string $text, array $attachments, array $speaker): void
    {
        $attachments[] = ['type' => 'chat_speaker', 'payload' => $speaker];
        $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
    }
}
