<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строки чата о поддержании заклинания.
 */
final class GameActiveSpellChat
{
    /**
     * Создаёт билдер сообщений.
     *
     * @param IChats $chats Чат игры.
     * @param GameStrikeChat $tokens Токены участников.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
        private readonly GameStrikeChat $tokens,
    ) {
    }

    /**
     * Пишет, что эффект начал поддерживаться.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveSpellRecord $spell Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function createdAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveSpellRecord $spell,
        array $thread,
    ): void {
        $token = $this->tokens->getEntityToken($spell->getCasterKind(), $spell->getCasterId());
        $this->send(
            $game,
            $actorUserId,
            $token . ' поддерживает ' . $this->rule($spell) . '.',
            $this->attachments($thread, $spell),
        );
    }

    /**
     * Пишет, что поддержание выдало заряд.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveSpellRecord $spell Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param string $chargeName Название состояния заряда.
     * @param int $value Сила заряда после выдачи.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function chargeGrantedAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveSpellRecord $spell,
        array $thread,
        string $chargeName,
        int $value,
    ): void {
        $token = $this->tokens->getEntityToken($spell->getCasterKind(), $spell->getCasterId());
        $this->send(
            $game,
            $actorUserId,
            $token . ' получает ' . $chargeName . ' (' . $this->rule($spell) . '): ' . $value . '.',
            $this->attachments($thread, $spell),
        );
    }

    /**
     * Пишет, что поддержание продолжено.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveSpellRecord $spell Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param int $actionCost Потраченные ОД.
     * @param int $manaCost Потраченная мана.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function sustainedAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveSpellRecord $spell,
        array $thread,
        int $actionCost = 0,
        int $manaCost = 0,
    ): void {
        $token = $this->tokens->getEntityToken($spell->getCasterKind(), $spell->getCasterId());
        $prices = [];
        if ($actionCost > 0) {
            $prices[] = $actionCost . ' ОД';
        }

        if ($manaCost > 0) {
            $prices[] = $manaCost . ' маны';
        }

        $cost = $prices === [] ? '' : ' за ' . implode(' и ', $prices);
        $this->send(
            $game,
            $actorUserId,
            $token . ' продолжает поддерживать ' . $this->rule($spell) . $cost . '.',
            $this->attachments($thread, $spell),
        );
    }

    /**
     * Пишет, что поддержание прекращено.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveSpellRecord $spell Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param bool $lostSource Источник пропал сам.
     * @param string|null $shortage Чего не хватило для оплаты.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function droppedAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveSpellRecord $spell,
        array $thread,
        bool $lostSource = false,
        ?string $shortage = null,
    ): void {
        $token = $this->tokens->getEntityToken($spell->getCasterKind(), $spell->getCasterId());
        if ($lostSource) {
            $text = 'Источник недоступен: ' . $this->rule($spell) . ' у ' . $token . ' спадает.';
        } elseif ($shortage !== null) {
            $text = 'Не хватает ' . $shortage . ': ' . $this->rule($spell) . ' у ' . $token . ' спадает.';
        } else {
            $text = $token . ' перестаёт поддерживать ' . $this->rule($spell) . '.';
        }

        $this->send($game, $actorUserId, $text, $this->attachments($thread, $spell));
    }

    /**
     * Ссылка на карточку.
     *
     * @param GameActiveSpellRecord $spell Строка.
     *
     * @return string Токен правила.
     */
    private function rule(GameActiveSpellRecord $spell): string
    {
        return '[[rule:' . $spell->getSpellCode() . ']]';
    }

    /**
     * Вложения треда и спикера.
     *
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param GameActiveSpellRecord $spell Строка.
     *
     * @return list<array{type: string, payload: array<string, mixed>}> Вложения.
     */
    private function attachments(array $thread, GameActiveSpellRecord $spell): array
    {
        return [
            ['type' => 'chat_system', 'payload' => ['thread' => $thread]],
            ['type' => 'chat_speaker', 'payload' => $this->tokens->getSpeakerOf(
                $spell->getCasterKind(),
                $spell->getCasterId(),
            )],
        ];
    }

    /**
     * Отправляет сообщение и переводит ошибки чата.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или участник чужой.
     */
    private function send(GameRecord $game, int $actorUserId, string $text, array $attachments): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        try {
            $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }
}
