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
        $chatId = $this->chatId($game);
        $token = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $text = $this->actionText($token, $actionRuleCode, $stepName, $spent);
        $thread = GameCombatThread::buildTurn($battleId, $round, $activeIndex);
        $attachments = $this->attachments($thread, $actorKind, $actorId);
        $this->send($chatId, $actorUserId, $text, $attachments);
        foreach ($rolls as $entry) {
            $this->send($chatId, $actorUserId, $token . ' ' . $entry['text'], [
                ['type' => 'roll', 'payload' => $entry['roll']],
                ...$attachments,
            ]);
        }
    }

    /**
     * Пишет объявление прикрытия в тред открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param GameBattleRecord $battle Бой.
     * @param string $threadId Нить атаки.
     * @param string $actionRuleCode Карточка реакции.
     * @param array{type: string, id: int} $target Прикрываемый.
     * @param string $reaction Выбранная защита.
     * @param string $blockItemRuleCode Предмет блока.
     * @param int $cost Стоимость прикрытия.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function postStrikeCover(
        GameRecord $game,
        int $actorUserId,
        string $actorKind,
        int $actorId,
        GameBattleRecord $battle,
        string $threadId,
        string $actionRuleCode,
        array $target,
        string $reaction,
        string $blockItemRuleCode,
        int $cost,
    ): void {
        $chatId = $this->chatId($game);
        $thread = GameCombatThread::buildAttack($battle, $threadId);
        $text = $this->coverText($actorKind, $actorId, $actionRuleCode, $target, $reaction, $blockItemRuleCode, $cost);
        $this->send($chatId, $actorUserId, $text, $this->attachments($thread, $actorKind, $actorId));
    }

    /**
     * Пишет объявление реакции в тред открытого удара.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param GameBattleRecord $battle Бой.
     * @param string $threadId Нить атаки.
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
        string $threadId,
        string $actionRuleCode,
        string $side,
        array $sideActor,
        int $spent,
        int $advantage,
    ): void {
        $chatId = $this->chatId($game);
        $thread = GameCombatThread::buildAttack($battle, $threadId);
        $text = $this->reactionText($actorKind, $actorId, $actionRuleCode, $side, $sideActor, $spent, $advantage);
        $this->send($chatId, $actorUserId, $text, $this->attachments($thread, $actorKind, $actorId));
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
     * Текст действия.
     *
     * @param string $token Токен актора.
     * @param string $actionRuleCode Карточка.
     * @param string|null $stepName Имя шага.
     * @param int $spent Потрачено ОД.
     *
     * @return string Текст.
     */
    private function actionText(string $token, string $actionRuleCode, ?string $stepName, int $spent): string
    {
        $step = $stepName === null || $stepName === '' ? '' : ' (шаг «' . $stepName . '»)';

        return $token . ' совершает действие [[rule:' . $actionRuleCode . ']]'
            . $step . ' за ' . $spent . 'ОД.';
    }

    /**
     * Текст прикрытия.
     *
     * @param string $actorKind Вид актора.
     * @param int $actorId Актор.
     * @param string $actionRuleCode Карточка.
     * @param array{type: string, id: int} $target Прикрываемый.
     * @param string $reaction Защита.
     * @param string $blockItemRuleCode Предмет блока.
     * @param int $cost Цена.
     *
     * @return string Текст.
     */
    private function coverText(
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        array $target,
        string $reaction,
        string $blockItemRuleCode,
        int $cost,
    ): string {
        $actor = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $covered = $this->strikeChat->getEntityToken($target['type'], $target['id']);
        $block = $blockItemRuleCode !== '' ? ' с [[rule:' . $blockItemRuleCode . ']]' : '';

        return $actor . ' объявляет [[rule:' . $actionRuleCode . ']]: прикрывает '
            . $covered . ', [[rule:' . $reaction . ']]' . $block . ' за ' . $cost . ' ОД при старте.';
    }

    /**
     * Текст реакции.
     *
     * @param string $actorKind Вид актора.
     * @param int $actorId Актор.
     * @param string $actionRuleCode Карточка.
     * @param string $side Сторона удара.
     * @param array{type: string, id: int} $sideActor Участник стороны.
     * @param int $spent Потрачено ОД.
     * @param int $advantage Преимущество.
     *
     * @return string Текст.
     */
    private function reactionText(
        string $actorKind,
        int $actorId,
        string $actionRuleCode,
        string $side,
        array $sideActor,
        int $spent,
        int $advantage,
    ): string {
        $actor = $this->strikeChat->getEntityToken($actorKind, $actorId);
        $target = $this->strikeChat->getEntityToken($sideActor['type'], $sideActor['id']);
        $sideText = $side === 'attacker' ? 'атакующего' : 'защищающегося';

        return $actor . ' объявляет реакцию [[rule:' . $actionRuleCode . ']]: поддерживает '
            . $sideText . ' ' . $target . ' за ' . $spent . ' ОД, +' . $advantage . ' преимущества.';
    }

    /**
     * Вложения треда и спикера.
     *
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param string $actorKind Вид актора.
     * @param int $actorId Актор.
     *
     * @return list<array{type: string, payload: array<string, mixed>}> Вложения.
     */
    private function attachments(array $thread, string $actorKind, int $actorId): array
    {
        return [
            ['type' => 'chat_system', 'payload' => ['thread' => $thread]],
            ['type' => 'chat_speaker', 'payload' => $this->strikeChat->getSpeakerOf($actorKind, $actorId)],
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
