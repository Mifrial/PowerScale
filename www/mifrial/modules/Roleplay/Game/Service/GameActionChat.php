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
     * @param int $battleId Бой.
     * @param int $round Раунд.
     * @param int $activeIndex Индекс хода.
     * @param list<array{roll: array<string, mixed>, text: string}> $rolls Проверки прироста состояний.
     * @param array<string, mixed>|null $process Шаг процесса.
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
        ?array $process = null,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            throw new GameInvalidException('Game chat is missing');
        }

        $token = $this->strikeChat->getEntityToken($actorKind, $actorId);
        if (is_array($process)) {
            $verb = $process['step']->getCode() === $process['spec']->getStartStepCode()
                ? 'начинает'
                : 'продолжает';
            $text = $token . ' ' . $verb . ' процесс [[rule:' . $process['processRuleCode'] . ']], шаг «'
                . $process['step']->getName() . '» — ' . $spent . 'ОД.';
        } else {
            $text = $token . ' совершает действие [[rule:' . $actionRuleCode . ']] за ' . $spent . 'ОД.';
        }

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
}
