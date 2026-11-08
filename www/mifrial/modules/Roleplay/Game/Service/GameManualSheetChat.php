<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameBattleRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Сообщения ручной правки листа в чат игры.
 */
final class GameManualSheetChat
{
    /**
     * Создаёт отправку.
     *
     * @param IChats $chats Чат игры.
     * @param GameSessionRepository $sessions Сессии.
     * @param GameBattleRepository $battles Бои.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
        private readonly GameSessionRepository $sessions,
        private readonly GameBattleRepository $battles,
    ) {
    }

    /**
     * Одно текстовое сообщение.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет.
     */
    public function say(GameRecord $game, int $actorUserId, string $text): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $thread = $this->turnThread($game) ?? $this->sessionThread($game);
        $attachments = $thread === null ? [] : [['type' => 'chat_system', 'payload' => ['thread' => $thread]]];
        $this->chats->send($chatId, $actorUserId, $text, $attachments);
    }

    /**
     * Сообщение проверки прироста состояния.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $subject Имя цели.
     * @param array{roll: array<string, mixed>, text: string} $entry Бросок и текст.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет.
     */
    public function sayRoll(GameRecord $game, int $actorUserId, string $subject, array $entry): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $thread = $this->turnThread($game) ?? $this->sessionThread($game);
        $attachments = [
            ['type' => 'roll', 'payload' => $entry['roll']],
        ];
        if ($thread !== null) {
            $attachments[] = ['type' => 'chat_system', 'payload' => ['thread' => $thread]];
        }

        $this->chats->send($chatId, $actorUserId, $subject . ' ' . $entry['text'], $attachments);
    }

    /**
     * Тред текущего хода открытого боя.
     *
     * @param GameRecord $game Игра.
     *
     * @return array{id: string, parentId: string, kind: string}|null Тред или null.
     */
    private function turnThread(GameRecord $game): ?array
    {
        $sessionId = $this->sessions->findSessionId($game->getId());
        $ids = $sessionId === null ? [] : $this->battles->findIdsBySession($sessionId);
        $battleId = $ids === [] ? null : $ids[array_key_last($ids)];
        if ($battleId === null) {
            return null;
        }

        $battle = $this->battles->find($battleId);
        $order = $battle?->getTurnOrder();
        if ($battle === null || $battle->getRound() < 1 || $order === null) {
            return null;
        }

        $round = $battle->getRound();
        $index = $battle->getActiveIndex();

        return [
            'id' => 'b' . $battleId . '-r' . $round . '-t' . $index,
            'parentId' => 'b' . $battleId . '-r' . $round,
            'kind' => 'turn',
        ];
    }

    /**
     * Тред открытой сессии, когда боя нет.
     *
     * @param GameRecord $game Игра.
     *
     * @return array{id: string, kind: string}|null Тред или null.
     */
    private function sessionThread(GameRecord $game): ?array
    {
        $sessionId = $this->sessions->findSessionId($game->getId());
        if ($sessionId === null) {
            return null;
        }

        return ['id' => 's' . $sessionId, 'kind' => 'session'];
    }
}
