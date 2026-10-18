<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Системные сообщения боя: раунд, инициатива, ход. Лист не пишет.
 */
final class GameCombatNotices
{
    public function __construct(
        private readonly IChats $chats,
    ) {
    }

    /**
     * Заголовок сессии. После stop в этот тред больше ничего не пишется.
     */
    public function sessionOpened(GameRecord $game, int $actorUserId, int $sessionId): void
    {
        $thread = ['id' => 's' . $sessionId, 'kind' => 'session'];
        $this->send($game, $actorUserId, 'Сессия от ' . date('d.m.Y H:i'), [], $thread, 'highlighted');
    }

    /**
     * Новый раунд, кубы инициативы в строке порядка и первый ход.
     *
     * @param list<array<string, mixed>> $order Порядок.
     */
    public function opened(GameRecord $game, int $actorUserId, int $sessionId, int $battleId, array $order): void
    {
        $battle = ['id' => 'b' . $battleId, 'parentId' => 's' . $sessionId, 'kind' => 'battle'];
        $this->send($game, $actorUserId, 'Бой', [], $battle, 'default');
        $round = ['id' => 'b' . $battleId . '-r1', 'parentId' => $battle['id'], 'kind' => 'round'];
        $this->send($game, $actorUserId, 'Новый раунд: 1', [], $round, 'highlighted');
        $initiative = ['id' => 'b' . $battleId . '-init', 'parentId' => $round['id'], 'kind' => 'initiative'];
        $rolls = [];
        foreach ($order as $entry) {
            if (!is_array($entry['faces'] ?? null)) {
                continue;
            }

            $rolls[] = ['type' => 'roll', 'payload' => $this->rollPayload($entry)];
        }

        $this->send($game, $actorUserId, $this->orderText($order), $rolls, $initiative, null);
        $first = $order[0] ?? null;
        if (is_array($first)) {
            $turn = ['id' => 'b' . $battleId . '-r1-t0', 'parentId' => $round['id'], 'kind' => 'turn'];
            $this->send($game, $actorUserId, 'Ходит ' . $this->nameOf($first), [], $turn, 'default');
        }
    }

    /**
     * Следующий ход и новый раунд, если круг замкнулся.
     */
    public function passed(GameRecord $game, int $actorUserId, int $battleId, int $round, int $index, string $name): void
    {
        $roundThread = ['id' => 'b' . $battleId . '-r' . $round, 'parentId' => 'b' . $battleId, 'kind' => 'round'];
        if ($index === 0 && $round > 1) {
            $this->send($game, $actorUserId, 'Новый раунд: ' . $round, [], $roundThread, 'highlighted');
        }

        $turn = [
            'id' => 'b' . $battleId . '-r' . $round . '-t' . $index,
            'parentId' => $roundThread['id'],
            'kind' => 'turn',
        ];
        $this->send($game, $actorUserId, 'Ходит ' . $name, [], $turn, 'default');
    }

    /**
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param array{id: string, kind: string, parentId?: string} $thread Тред.
     */
    private function send(
        GameRecord $game,
        int $actorUserId,
        string $text,
        array $attachments,
        array $thread,
        ?string $kind,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $payload = ['thread' => $thread];
        if ($kind !== null) {
            $payload['kind'] = $kind;
        }

        $attachments[] = ['type' => 'chat_system', 'payload' => $payload];
        try {
            $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * @param list<array<string, mixed>> $order Порядок.
     */
    private function orderText(array $order): string
    {
        $parts = [];
        foreach ($order as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $parts[] = $this->nameOf($entry) . ' (' . $this->scoreOf($entry) . ')';
        }

        return 'Порядок инициативы: ' . implode(', ', $parts) . '.';
    }

    /**
     * @param array<string, mixed> $entry Запись.
     */
    private function nameOf(array $entry): string
    {
        $name = $entry['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : 'Участник';
    }

    /**
     * @param array<string, mixed> $entry Запись.
     */
    private function scoreOf(array $entry): string
    {
        if (is_int($entry['rating'] ?? null)) {
            return (string) $entry['rating'];
        }

        $roll = $entry['roll'] ?? null;
        if (is_array($roll) && is_int($roll['base'] ?? null)) {
            return (string) $roll['base'];
        }

        return '—';
    }

    /**
     * @param array<string, mixed> $entry Запись порядка.
     *
     * @return array<string, mixed> Вложение броска.
     */
    private function rollPayload(array $entry): array
    {
        $payload = $entry;
        unset($payload['type'], $payload['id'], $payload['name']);

        return $payload;
    }
}
