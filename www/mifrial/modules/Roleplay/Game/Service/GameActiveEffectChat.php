<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Строки чата о наложении и истечении длительного эффекта.
 */
final class GameActiveEffectChat
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
     * Пишет, что эффект наложен.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveEffectRecord $effect Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function appliedAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveEffectRecord $effect,
        array $thread,
    ): void {
        $source = '[[rule:' . $effect->getSourceRuleCode() . ']]';
        $target = $this->tokens->getEntityToken($effect->getTargetKind(), $effect->getTargetId());
        $applierKey = $effect->getApplierKey();
        if ($applierKey === null) {
            $this->send(
                $game,
                $actorUserId,
                $source . ' накладывается на ' . $target . '.',
                $this->attachments($thread, $effect->getTargetKind(), $effect->getTargetId()),
            );

            return;
        }

        $text = $effect->getTargetKey() === $applierKey
            ? $this->tokens->getEntityToken($effect->getTargetKind(), $effect->getTargetId())
                . ' накладывает ' . $source . ' на себя.'
            : $this->tokens->getEntityToken((string) $effect->getApplierKind(), (int) $effect->getApplierId())
                . ' накладывает ' . $source . ' на ' . $target . '.';
        $this->send(
            $game,
            $actorUserId,
            $text,
            $this->attachments($thread, (string) $effect->getApplierKind(), (int) $effect->getApplierId()),
        );
    }

    /**
     * Пишет, что эффект спал.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameActiveEffectRecord $effect Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель чужой.
     */
    public function expiredAt(
        GameRecord $game,
        int $actorUserId,
        GameActiveEffectRecord $effect,
        array $thread,
    ): void {
        $source = '[[rule:' . $effect->getSourceRuleCode() . ']]';
        $target = $this->tokens->getEntityToken($effect->getTargetKind(), $effect->getTargetId());
        $this->send(
            $game,
            $actorUserId,
            $source . ' на ' . $target . ' спадает.',
            $this->attachments($thread, $effect->getTargetKind(), $effect->getTargetId()),
        );
    }

    /**
     * Вложения треда и спикера.
     *
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param string $kind character или npc.
     * @param int $id Id.
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
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     * @param list<array{type: string, payload: array<string, mixed>}> $attachments Вложения.
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
