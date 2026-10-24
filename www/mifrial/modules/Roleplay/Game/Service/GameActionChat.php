<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Сообщение чата о совершённом действии.
 */
final class GameActionChat
{
    /**
     * Создаёт билдер сообщения.
     *
     * @param IChats $chats Чат игры.
     * @param GameStrikeChat $strikeChat Токены участников.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
        private readonly GameStrikeChat $strikeChat,
    ) {
    }

    /**
     * Пишет сообщение о действии актора.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param string $actionRuleCode Карточка действия.
     * @param int $spent Потрачено ОД.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        int $spent,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $token = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $text = $token . ' совершает действие [[rule:' . $actionRuleCode . ']] за ' . $spent . 'ОД.';
        try {
            $this->chats->send($chatId, $actorUserId, $text, []);
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }
}
