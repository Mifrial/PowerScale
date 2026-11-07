<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Запись хода удара в чат игры.
 */
final class GameStrikeConsequenceChat
{
    /**
     * Создаёт запись в чат.
     *
     * @param IChats $chats Чат игры.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
    ) {
    }

    /**
     * Пишет цепочку в чат игры: действие, нарратив, бросок, итог, Воля, увечье.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param bool $announce Писать действие.
     * @param string $action Действие.
     * @param string $strike Нарратив.
     * @param array{base: int, size: int}|array<string, mixed> $hitRoll Бросок попадания.
     * @param string $result Итог.
     * @param array<string, mixed> $calc Payload attack-calc.
     * @param array{roll: array<string, mixed>, text: string}|null $will Проверка истощения.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param array<string, mixed>|null $defenseRoll Бросок защиты.
     * @param string|null $parentId Родительский ход.
     * @param array{text: string, roll: array<string, mixed>}|null $beforeOutcome Проверка до итога.
     * @param list<array{text: string, roll: array<string, mixed>}> $preRollChecks Проверки перед общими бросками.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        bool $announce,
        string $action,
        string $strike,
        array $hitRoll,
        string $result,
        array $calc,
        ?array $will = null,
        ?array $maim = null,
        ?array $speaker = null,
        ?array $defenseRoll = null,
        ?string $parentId = null,
        ?array $beforeOutcome = null,
        array $preRollChecks = [],
    ): void {
        $chatId = $this->chatId($game);
        $thread = $this->attackThread($threadId, $parentId);
        try {
            $this->announce($chatId, $actorUserId, $announce, $action, $strike, $thread, $speaker);
            $this->sendBeforeOutcome(
                $chatId, $actorUserId, $preRollChecks, $hitRoll, $defenseRoll, $beforeOutcome, $thread, $speaker,
            );
            $this->sendOutcome($chatId, $actorUserId, $result, $calc, $will, $maim, $thread, $speaker);
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Пишет набор текстовых строк в чат игры одной нитью.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param array<int, string> $lines Тексты.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function postLines(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        array $lines,
        ?array $speaker = null,
        ?string $parentId = null,
    ): void {
        $chatId = $this->chatId($game);
        $thread = $this->attackThread($threadId, $parentId);
        try {
            foreach ($lines as $line) {
                $this->sendText($chatId, $actorUserId, $line, $thread, $speaker);
            }
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Пишет набор бросков в чат игры одной нитью.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param list<array<string, mixed>> $rolls Броски одной строкой.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function postRolls(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        array $rolls,
        ?array $speaker = null,
        ?string $parentId = null,
    ): void {
        $chatId = $this->chatId($game);
        try {
            $this->sendRolls($chatId, $actorUserId, $rolls, $this->attackThread($threadId, $parentId), $speaker);
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Проверка с текстом в одном сообщении.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param string $text Текст проверки.
     * @param array<string, mixed> $roll Бросок.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function postCheck(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        string $text,
        array $roll,
        ?array $speaker = null,
        ?string $parentId = null,
    ): void {
        $chatId = $this->chatId($game);
        try {
            $this->sendCheck(
                $chatId,
                $actorUserId,
                ['text' => $text, 'roll' => $roll],
                $this->attackThread($threadId, $parentId),
                $speaker,
            );
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Пишет итог удара, проверку истощения и увечье в чат игры.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Нить.
     * @param string $result Итог.
     * @param array<string, mixed> $calc Payload attack-calc.
     * @param array{roll: array<string, mixed>, text: string}|null $will Проверка истощения.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     * @param string|null $parentId Родительский ход.
     *
     * @return void
     *
     * @throws GameInvalidException Если чат игры или его участник недоступен.
     */
    public function postOutcome(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        string $result,
        array $calc,
        ?array $will = null,
        ?array $maim = null,
        ?array $speaker = null,
        ?string $parentId = null,
    ): void {
        $chatId = $this->chatId($game);
        try {
            $this->sendOutcome(
                $chatId,
                $actorUserId,
                $result,
                $calc,
                $will,
                $maim,
                $this->attackThread($threadId, $parentId),
                $speaker,
            );
        } catch (ChatNotFoundException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Пишет предварительные проверки, броски и проверку до итога.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param list<array{text: string, roll: array<string, mixed>}> $preRollChecks Проверки перед бросками.
     * @param array{base: int, size: int}|array<string, mixed> $hitRoll Бросок попадания.
     * @param array<string, mixed>|null $defenseRoll Бросок защиты.
     * @param array{text: string, roll: array<string, mixed>}|null $beforeOutcome Проверка до итога.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendBeforeOutcome(
        int $chatId,
        int $actorUserId,
        array $preRollChecks,
        array $hitRoll,
        ?array $defenseRoll,
        ?array $beforeOutcome,
        array $thread,
        ?array $speaker,
    ): void {
        foreach ($preRollChecks as $check) {
            $this->sendCheck($chatId, $actorUserId, $check, $thread, $speaker);
        }

        $this->sendRollsWithDefense($chatId, $actorUserId, $hitRoll, $defenseRoll, $thread, $speaker);
        if ($beforeOutcome !== null) {
            $this->sendCheck($chatId, $actorUserId, $beforeOutcome, $thread, $speaker);
        }
    }

    /**
     * Пишет действие и нарратив удара.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param bool $announce Писать действие.
     * @param string $action Действие.
     * @param string $strike Нарратив.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function announce(
        int $chatId,
        int $actorUserId,
        bool $announce,
        string $action,
        string $strike,
        array $thread,
        ?array $speaker,
    ): void {
        if (!$announce) {
            return;
        }

        if ($action !== '') {
            $this->sendText($chatId, $actorUserId, $action, $thread, $speaker);
        }

        if ($strike !== '') {
            $this->sendText($chatId, $actorUserId, $strike, $thread, $speaker);
        }
    }

    /**
     * Пишет бросок попадания и бросок защиты.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param array{base: int, size: int}|array<string, mixed> $hitRoll Бросок попадания.
     * @param array<string, mixed>|null $defenseRoll Бросок защиты.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendRollsWithDefense(
        int $chatId,
        int $actorUserId,
        array $hitRoll,
        ?array $defenseRoll,
        array $thread,
        ?array $speaker,
    ): void {
        $rolls = [$hitRoll];
        if ($defenseRoll !== null) {
            $rolls[] = $defenseRoll;
        }

        $this->sendRolls($chatId, $actorUserId, $rolls, $thread, $speaker);
    }

    /**
     * Идентификатор чата игры.
     *
     * @param GameRecord $game Игра.
     *
     * @return int Идентификатор чата.
     *
     * @throws GameInvalidException Если чат игры не задан.
     */
    private function chatId(GameRecord $game): int
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is invalid');
        }

        return $chatId;
    }

    /**
     * Тред атаки с родительским ходом.
     *
     * @param string $threadId Идентификатор атаки.
     * @param string|null $parentId Родительский ход.
     *
     * @return array{id: string, kind: string, parentId?: string} Тред.
     */
    private function attackThread(string $threadId, ?string $parentId): array
    {
        $thread = ['id' => $threadId, 'kind' => 'attack'];
        if ($parentId !== null) {
            $thread['parentId'] = $parentId;
        }

        return $thread;
    }

    /**
     * Отправляет текст в чат игры в нити атаки.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param string $text Текст.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendText(int $chatId, int $actorUserId, string $text, array $thread, ?array $speaker): void
    {
        $this->chats->send($chatId, $actorUserId, $text, $this->withThread([], $thread, $speaker), null);
    }

    /**
     * Отправляет броски одним сообщением.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param list<array<string, mixed>> $rolls Броски.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendRolls(int $chatId, int $actorUserId, array $rolls, array $thread, ?array $speaker): void
    {
        $attachments = [];
        foreach ($rolls as $roll) {
            $attachments[] = ['type' => 'roll', 'payload' => $roll];
        }

        $this->chats->send($chatId, $actorUserId, '', $this->withThread($attachments, $thread, $speaker), null);
    }

    /**
     * Отправляет проверку с текстом одним сообщением.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param array{text: string, roll: array<string, mixed>} $check Проверка.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendCheck(int $chatId, int $actorUserId, array $check, array $thread, ?array $speaker): void
    {
        $text = $check['text'];
        $roll = $check['roll'];
        $attachments = [['type' => 'roll', 'payload' => $roll]];
        $this->chats->send($chatId, $actorUserId, $text, $this->withThread($attachments, $thread, $speaker), null);
    }

    /**
     * Отправляет итог удара и приложенные проверки в чат.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Актор.
     * @param string $result Итог.
     * @param array<string, mixed> $calc Payload.
     * @param array{roll: array<string, mixed>, text: string}|null $will Воля.
     * @param array{roll: array<string, mixed>, text: string, details: array<string, mixed>}|null $maim Увечье.
     * @param array{id: string, kind: string} $thread Нить.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string}|null $speaker Спикер.
     *
     * @return void
     */
    private function sendOutcome(
        int $chatId,
        int $actorUserId,
        string $result,
        array $calc,
        ?array $will,
        ?array $maim,
        array $thread,
        ?array $speaker,
    ): void {
        $this->chats->send($chatId, $actorUserId, $result, $this->withThread([[
            'type' => 'attack-calc',
            'payload' => $calc,
        ],
        ], $thread, $speaker), null);
        if ($will !== null) {
            $this->chats->send($chatId, $actorUserId, '', $this->withThread([['type' => 'roll', 'payload' => $will['roll']]], $thread, $speaker), null);
            $this->chats->send($chatId, $actorUserId, $will['text'], $this->withThread([], $thread, $speaker), null);
        }

        if ($maim !== null) {
            $this->chats->send($chatId, $actorUserId, '', $this->withThread([['type' => 'roll', 'payload' => $maim['roll']]], $thread, $speaker), null);
            $this->chats->send($chatId, $actorUserId, $maim['text'], $this->withThread([[
                'type' => 'injury-details',
                'payload' => $maim['details'],
            ],
            ], $thread, $speaker), null);
        }
    }

    /**
     * Добавляет к вложениям нить атаки и спикера.
     *
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param array{id: string, kind: string, parentId?: string} $thread Тред атаки.
     * @param array<string, mixed>|null $speaker Говорящий персонаж или NPC.
     *
     * @return list<array<string, mixed>> Вложения с носителем треда.
     */
    private function withThread(array $attachments, array $thread, ?array $speaker): array
    {
        $attachments[] = ['type' => 'chat_system', 'payload' => ['thread' => $thread]];
        if ($speaker !== null) {
            $attachments[] = ['type' => 'chat_speaker', 'payload' => $speaker];
        }

        return $attachments;
    }
}
