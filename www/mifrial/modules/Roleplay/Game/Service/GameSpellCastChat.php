<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Сообщения чата о серверном касте.
 */
final class GameSpellCastChat
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
     * Пишет объявление, бросок, урон и последствия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $check Итог проверки.
     * @param array<string, mixed> $damage Итог урона.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function post(
        GameRecord $game,
        int $actorUserId,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        array $check,
        array $damage,
    ): void {
        $chatId = $this->chatId($game);
        $thread = GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $attachments = $this->attachments($thread, $prepared->casterKind, $prepared->casterId);
        $token = $this->tokens->getEntityToken($prepared->casterKind, $prepared->casterId);
        $spell = '[[rule:' . $prepared->spellCode . ']]';
        $targetToken = $prepared->target === null
            ? ''
            : $this->tokens->getEntityToken($prepared->target['type'], $prepared->target['id']);

        $this->send(
            $chatId,
            $actorUserId,
            $token . ' сотворяет ' . $spell . ' за ' . $prepared->spent . ' ОД.',
            $attachments,
        );

        $roll = $check['roll'] ?? null;
        if (($check['needsCheck'] ?? false) === true && is_array($roll)) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' проходит проверку на сотворение.',
                [['type' => 'roll', 'payload' => $roll], ...$attachments],
            );
        }

        if (($check['castOk'] ?? false) !== true) {
            $this->send($chatId, $actorUserId, $token . ' не справляется с ' . $spell . '.', $attachments);

            return;
        }

        $injury = $damage['injury'] ?? null;
        if ($injury instanceof DimensionalNumber) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' поражает ' . $targetToken . ' на ' . $this->number($injury) . ' повреждения.',
                $attachments,
            );
        }

        $consequence = $damage['consequence'] ?? [];
        if (is_array($consequence)) {
            $this->postConsequence($chatId, $actorUserId, $token, $targetToken, $consequence, $attachments);
        }
    }

    /**
     * Воля и увечье цели.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $token Токен кастера.
     * @param string $targetToken Токен цели.
     * @param array<string, mixed> $consequence Последствия.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     */
    private function postConsequence(
        int $chatId,
        int $actorUserId,
        string $token,
        string $targetToken,
        array $consequence,
        array $attachments,
    ): void {
        $will = $consequence['will'] ?? null;
        if (is_array($will)) {
            $outcome = is_string($consequence['willOutcome'] ?? null) ? $consequence['willOutcome'] : '';
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' проверяет Волю' . ($outcome === '' ? '.' : ': ' . $outcome . '.'),
                [['type' => 'roll', 'payload' => $will], ...$attachments],
            );
        }

        $maim = $consequence['maim'] ?? null;
        if (is_array($maim)) {
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' получает увечье от ' . $token . '.',
                [['type' => 'roll', 'payload' => $maim], ...$attachments],
            );
        }
    }

    /**
     * Число в виде base|size.
     *
     * @param DimensionalNumber $number Число.
     *
     * @return string Текст.
     */
    private function number(DimensionalNumber $number): string
    {
        return $number->getBase() . '|' . $number->getSize();
    }

    /**
     * Чат игры.
     *
     * @param GameRecord $game Игра.
     *
     * @return int Id чата.
     *
     * @throws GameInvalidException Если чата нет.
     */
    private function chatId(GameRecord $game): int
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        return $chatId;
    }

    /**
     * Вложения треда и спикера.
     *
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param string $kind Вид кастера.
     * @param int $id Кастер.
     *
     * @return list<array{type: string, payload: array<string, mixed>}> Вложения.
     */
    private function attachments(array $thread, string $kind, int $id): array
    {
        return [
            ['type' => 'chat_system', 'payload' => ['thread' => $thread]],
            ['type' => 'chat_speaker', 'payload' => $this->tokens->getSpeakerOf($kind, $id)],
        ];
    }

    /**
     * Отправляет сообщение и переводит ошибки чата.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     *
     * @throws GameInvalidException Если участник чата чужой.
     */
    private function send(int $chatId, int $actorUserId, string $text, array $attachments): void
    {
        try {
            $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }
}
