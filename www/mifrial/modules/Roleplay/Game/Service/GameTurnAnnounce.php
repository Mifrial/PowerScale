<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Сообщения конца хода: действие, броски и передача хода.
 */
final class GameTurnAnnounce
{
    /**
     * Создаёт сообщения.
     *
     * @param GameCombatNotices $notices Сообщения боя.
     * @param GameActionChat $actions Сообщения карточки.
     * @param IChats $chats Чат игры.
     *
     * @return void
     */
    public function __construct(
        private readonly GameCombatNotices $notices,
        private readonly GameActionChat $actions,
        private readonly IChats $chats,
    ) {
    }

    /**
     * Сообщение об исполненном Ожидании.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param array{type: string, id: int} $current Текущий.
     * @param GameBattleRecord $battle Бой.
     * @param string $waitCode Карточка Ожидания.
     * @param array<string, mixed> $performed Исполнение.
     *
     * @return void
     */
    public function wait(
        GameRecord $game,
        int $actorUserId,
        array $current,
        GameBattleRecord $battle,
        string $waitCode,
        array $performed,
    ): void {
        $this->actions->post(
            $game,
            $actorUserId,
            $current['type'],
            $current['id'],
            $waitCode,
            $performed['spent'],
            $battle->getId(),
            $battle->getRound(),
            $battle->getActiveIndex(),
            $performed['rolls'],
        );
    }

    /**
     * Броски тиков в чат игры.
     *
     * @param GameRecord $game Игра.
     * @param list<array{roll: array<string, mixed>, text: string}> $rolls Сообщения.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или участника не принять.
     */
    public function rolls(GameRecord $game, array $rolls): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is invalid');
        }

        try {
            foreach ($rolls as $roll) {
                $payload = $roll['roll'];
                $attachment = isset($payload['roll']) ? [['type' => 'roll', 'payload' => $payload['roll']]] : [];
                $this->chats->send($chatId, $game->getOwnerId(), $roll['text'], $attachment, null);
            }
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Передаёт ход следующему.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $result Итог конца хода.
     *
     * @return void
     */
    public function passed(GameRecord $game, int $actorUserId, GameBattleRecord $battle, array $result): void
    {
        $order = $battle->getTurnOrder();
        $index = $result['activeIndex'] ?? 0;
        $entry = is_int($index) ? ($order[$index] ?? null) : null;
        $name = is_array($entry) && is_string($entry['name'] ?? null) && $entry['name'] !== ''
            ? $entry['name']
            : 'Участник';
        $round = is_int($result['round'] ?? null) ? $result['round'] : 1;
        $this->notices->passed($game, $actorUserId, $battle->getId(), $round, is_int($index) ? $index : 0, $name);
    }
}
