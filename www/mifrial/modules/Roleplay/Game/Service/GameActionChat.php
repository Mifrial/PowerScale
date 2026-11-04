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
     * @param int $battleId Бой.
     * @param int $round Раунд.
     * @param int $activeIndex Индекс хода.
     * @param list<array{roll: array<string, mixed>, text: string}> $rolls Проверки прироста состояний.
     * @param string|null $stepName Имя описательного шага процесса или null.
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
        int $battleId,
        int $round,
        int $activeIndex,
        array $rolls = [],
        ?string $stepName = null,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $token = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $step = $stepName === null || $stepName === '' ? '' : ' (шаг «' . $stepName . '»)';
        $text = $token . ' совершает действие [[rule:' . $actionRuleCode . ']]'
            . $step . ' за ' . $spent . 'ОД.';

        $thread = GameCombatThread::buildTurn($battleId, $round, $activeIndex);
        $attachments = [
            ['type' => 'chat_system', 'payload' => ['thread' => $thread]],
            ['type' => 'chat_speaker', 'payload' => $this->strikeChat->getSpeakerOf($actorKind, $actorId)],
        ];
        try {
            $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
            foreach ($rolls as $entry) {
                $roll = is_array($entry) ? ($entry['roll'] ?? null) : null;
                if (!is_array($roll)) {
                    continue;
                }

                $line = is_string($entry['text'] ?? null) ? $entry['text'] : '';
                $this->chats->send(
                    $chatId,
                    $actorUserId,
                    $token . ' ' . $line,
                    [
                        ['type' => 'roll', 'payload' => $roll],
                        ...$attachments,
                    ],
                    null,
                );
            }
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }

    /**
     * Пишет объявление реакции в тред открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param GameBattleRecord $battle Бой.
     * @param int $strikeId Удар.
     * @param string $actionRuleCode Карточка реакции.
     * @param string $side Сторона удара: attacker или defender.
     * @param array{type: string, id: int} $sideActor Участник выбранной стороны.
     * @param int $spent Потрачено ОД.
     * @param int $advantage Преимущество.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function postStrikeReaction(
        GameRecord $game,
        int $actorUserId,
        string $actorKind,
        int $actorId,
        GameBattleRecord $battle,
        int $strikeId,
        string $actionRuleCode,
        string $side,
        array $sideActor,
        int $spent,
        int $advantage,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $threadId = 'attack-' . $strikeId;
        $thread = GameCombatThread::buildAttack($battle, $threadId);
        $actor = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $target = $this->strikeChat->getEntityToken($sideActor['type'], $sideActor['id']);
        $sideText = $side === 'attacker' ? 'атакующего' : 'защищающегося';
        $text = $actor . ' объявляет реакцию [[rule:' . $actionRuleCode . ']]: поддерживает '
            . $sideText . ' ' . $target . ' за ' . $spent . ' ОД, +' . $advantage . ' преимущества.';
        $attachments = [
            ['type' => 'chat_system', 'payload' => ['thread' => $thread]],
            ['type' => 'chat_speaker', 'payload' => $this->strikeChat->getSpeakerOf($actorKind, $actorId)],
        ];
        try {
            $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
        } catch (ChatNotFoundException | ChatInvalidException $exception) {
            throw new GameInvalidException('Game chat member is invalid', $exception);
        }
    }
}
