<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameNpcRepository;

/**
 * Тред проверки: объявление, бросок и результат.
 */
final class GameCheckChat
{
    /**
     * Создаёт билдеры.
     *
     * @param IChats $chats Чат игры.
     * @param ICharacters $characters Персонажи.
     * @param GameNpcRepository $npcs NPC игры.
     *
     * @return void
     */
    public function __construct(
        private readonly IChats $chats,
        private readonly ICharacters $characters,
        private readonly GameNpcRepository $npcs,
    ) {
    }

    /**
     * Объявление проверки.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param array{type: string, id: int} $initiator Инициатор.
     * @param array{type: string, id: int} $opponent Цель.
     * @param string $ruleCode Карточка проверки.
     * @param int $spent Потрачено жетонов концентрации.
     *
     * @return void
     */
    public function postProposal(
        GameRecord $game,
        int $actorUserId,
        array $thread,
        array $initiator,
        array $opponent,
        string $ruleCode,
        int $spent,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            'Совместная проверка [[rule:' . $ruleCode . ']] между '
                . $this->jointName($initiator, $spent) . ' и ' . $this->tokenOf($opponent) . '.',
            [],
            $this->speakerOf($initiator),
            $thread,
        );
    }

    /**
     * Отказ от предложения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param array{type: string, id: int} $side Отказавшаяся сторона.
     * @param string $ruleCode Карточка проверки.
     *
     * @return void
     */
    public function postDeclined(GameRecord $game, int $actorUserId, array $thread, array $side, string $ruleCode): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($side) . ' отклоняет проверку [[rule:' . $ruleCode . ']].',
            [],
            $this->speakerOf($side),
            $thread,
        );
    }

    /**
     * Отмена предложения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param array{type: string, id: int} $side Отменившая сторона.
     * @param string $ruleCode Карточка проверки.
     *
     * @return void
     */
    public function postCancelled(GameRecord $game, int $actorUserId, array $thread, array $side, string $ruleCode): void
    {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $this->tokenOf($side) . ' отменяет проверку [[rule:' . $ruleCode . ']].',
            [],
            $this->speakerOf($side),
            $thread,
        );
    }

    /**
     * Броски совместной проверки и итог.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param array<string, mixed> $proposal Предложение.
     * @param string $ruleCode Карточка проверки.
     * @param array<string, array<string, mixed>> $rolls Броски сторон.
     * @param int $initiatorSpent Жетоны инициатора.
     * @param int $opponentSpent Жетоны цели.
     *
     * @return void
     */
    public function postResolution(
        GameRecord $game,
        int $actorUserId,
        array $thread,
        array $proposal,
        string $ruleCode,
        array $rolls,
        int $initiatorSpent,
        int $opponentSpent,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $initiator = $proposal['initiator'] ?? null;
        $opponent = $proposal['opponent'] ?? null;
        if (!is_array($initiator) || !is_array($opponent)) {
            return;
        }

        $attachments = [];
        foreach (['initiator', 'opponent'] as $side) {
            $roll = $rolls[$side] ?? null;
            if (is_array($roll)) {
                $attachments[] = ['type' => 'roll', 'payload' => $roll];
            }
        }

        $this->send(
            $chatId,
            $actorUserId,
            'Броски проверки [[rule:' . $ruleCode . ']].',
            $attachments,
            $this->speakerOf($initiator),
            $thread,
        );
        $this->send(
            $chatId,
            $actorUserId,
            $this->jointResult($initiator, $opponent, $ruleCode, $rolls),
            [],
            $this->speakerOf($initiator),
            $thread,
        );
    }

    /**
     * Соло-проверка: объявление, бросок и результат.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор сообщения.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param array{type: string, id: int} $participant Участник.
     * @param string $ruleCode Карточка проверки.
     * @param array<string, mixed> $thrown Бросок.
     * @param int $spent Жетоны концентрации.
     *
     * @return void
     */
    public function postSolo(
        GameRecord $game,
        int $actorUserId,
        array $thread,
        array $participant,
        string $ruleCode,
        array $thrown,
        int $spent,
    ): void {
        $chatId = $game->getGameChatId();
        if ($chatId === null) {
            return;
        }

        $speaker = $this->speakerOf($participant);
        $this->send(
            $chatId,
            $actorUserId,
            $this->withConcentration($participant, $spent)
                . ' совершает проверку [[rule:' . $ruleCode . ']] против сложности '
                . $this->dimensionalText($thrown['difficulty'] ?? null) . '.',
            [],
            $speaker,
            $thread,
        );
        $this->send($chatId, $actorUserId, '', [['type' => 'roll', 'payload' => $thrown]], $speaker, $thread);
        $this->send(
            $chatId,
            $actorUserId,
            $this->soloResult($participant, $ruleCode, $thrown),
            [],
            $speaker,
            $thread,
        );
    }

    /**
     * Итог соло-проверки.
     *
     * @param array{type: string, id: int} $participant Участник.
     * @param string $ruleCode Карточка.
     * @param array<string, mixed> $thrown Бросок.
     *
     * @return string Текст.
     */
    private function soloResult(array $participant, string $ruleCode, array $thrown): string
    {
        $token = $this->tokenOf($participant);
        $difficulty = $this->dimensionalText($thrown['difficulty'] ?? null);
        $rating = is_int($thrown['rating'] ?? null) ? $thrown['rating'] : 0;
        if (($thrown['success'] ?? false) === true) {
            return $token . ' преуспел в проверке [[rule:' . $ruleCode . ']] против сложности '
                . $difficulty . ' с ' . $rating . ' РУ.';
        }

        return $token . ' провалил проверку [[rule:' . $ruleCode . ']] против сложности '
            . $difficulty . ' (РУ ' . $rating . ').';
    }

    /**
     * Итог совместной проверки.
     *
     * @param array{type: string, id: int} $initiator Инициатор.
     * @param array{type: string, id: int} $opponent Цель.
     * @param string $ruleCode Карточка.
     * @param array<string, array<string, mixed>> $rolls Броски.
     *
     * @return string Текст.
     */
    private function jointResult(array $initiator, array $opponent, string $ruleCode, array $rolls): string
    {
        $left = is_array($rolls['initiator'] ?? null) ? $rolls['initiator'] : [];
        $right = is_array($rolls['opponent'] ?? null) ? $rolls['opponent'] : [];
        $leftSuccesses = $this->successesOf($left);
        $rightSuccesses = $this->successesOf($right);
        $leftPassed = ($left['success'] ?? false) === true;
        $rightPassed = ($right['success'] ?? false) === true;
        $tokens = [
            'initiator' => $this->tokenOf($initiator),
            'opponent' => $this->tokenOf($opponent),
        ];
        $score = $leftSuccesses . ' против ' . $rightSuccesses;
        if ($leftPassed && $rightPassed) {
            return 'Проверка [[rule:' . $ruleCode . ']] между ' . $tokens['initiator'] . ' и '
                . $tokens['opponent'] . ' закончилась ничьей: ' . $score . '.';
        }

        if (!$leftPassed && !$rightPassed) {
            return 'Никто не преуспел в проверке [[rule:' . $ruleCode . ']]: ' . $score . '.';
        }

        $winner = $leftPassed ? 'initiator' : 'opponent';
        $loser = $leftPassed ? 'opponent' : 'initiator';
        $rating = $this->ratingOf($leftPassed ? $left : $right);

        return $tokens[$winner] . ' преуспел в проверке [[rule:' . $ruleCode . ']] против '
            . $tokens[$loser] . ' с ' . $rating . ' РУ.';
    }

    /**
     * Рейтинг броска.
     *
     * @param array<string, mixed> $roll Бросок.
     *
     * @return int Рейтинг.
     */
    private function ratingOf(array $roll): int
    {
        return is_int($roll['rating'] ?? null) ? $roll['rating'] : 0;
    }

    /**
     * Успехи броска.
     *
     * @param array<string, mixed> $roll Бросок.
     *
     * @return int Успехи.
     */
    private function successesOf(array $roll): int
    {
        $base = $roll['roll']['base'] ?? null;
        if (is_int($base)) {
            return $base;
        }

        return is_int($roll['successes'] ?? null) ? $roll['successes'] : 0;
    }

    /**
     * Имя стороны совместной проверки с концентрацией.
     *
     * @param array{type: string, id: int} $participant Участник.
     * @param int $spent Жетоны.
     *
     * @return string Текст.
     */
    private function jointName(array $participant, int $spent): string
    {
        $token = $this->tokenOf($participant);
        if ($spent < 1) {
            return $token;
        }

        return $token . ' (концентрация ' . $spent . ')';
    }

    /**
     * Токен участника и упоминание концентрации.
     *
     * @param array{type: string, id: int} $participant Участник.
     * @param int $spent Жетоны.
     *
     * @return string Текст.
     */
    private function withConcentration(array $participant, int $spent): string
    {
        if ($spent < 1) {
            return $this->tokenOf($participant);
        }

        return $this->tokenOf($participant) . ', используя концентрацию (' . $spent . '),';
    }

    /**
     * Токен участника.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return string Токен.
     */
    private function tokenOf(array $participant): string
    {
        $kind = $participant['type'] === 'npc' ? 'npc' : 'character';
        $name = $this->nameOf($participant);

        return '[[' . $kind . ':' . $participant['id'] . ',' . $name . ']]';
    }

    /**
     * Спикер сообщения.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string} Спикер.
     */
    private function speakerOf(array $participant): array
    {
        $name = $this->nameOf($participant);
        if ($participant['type'] === 'npc') {
            return ['kind' => 'npc', 'npcId' => $participant['id'], 'npcName' => $name];
        }

        return ['kind' => 'character', 'characterId' => $participant['id'], 'characterName' => $name];
    }

    /**
     * Имя участника.
     *
     * @param array{type: string, id: int} $participant Участник.
     *
     * @return string Имя.
     */
    private function nameOf(array $participant): string
    {
        try {
            if ($participant['type'] === 'npc') {
                return $this->npcs->getById($participant['id'])->getName();
            }

            return $this->characters->get($participant['id'])->getName();
        } catch (CharacterNotFoundException | GameNotFoundException) {
            return '';
        }
    }

    /**
     * Размерное число для текста.
     *
     * @param mixed $value Пара base/size.
     *
     * @return string Текст.
     */
    private function dimensionalText(mixed $value): string
    {
        if (!is_array($value) || !is_int($value['base'] ?? null) || !is_int($value['size'] ?? null)) {
            return '?';
        }

        $base = (string) $value['base'];
        $size = $value['size'];
        if ($size === 0) {
            return $base;
        }

        $arrow = $size > 0 ? '↑' : '↓';
        $power = abs($size);

        return $base . $arrow . ($power > 1 ? (string) $power : '');
    }

    /**
     * Отправляет сообщение треда со спикером.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $text Текст.
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param array{kind: string, characterId?: int, characterName?: string, npcId?: int, npcName?: string} $speaker Спикер.
     * @param array{id: string, parentId: string, kind: string} $thread Тред.
     * @param string|null $kind Вид заголовка треда.
     *
     * @return void
     */
    private function send(
        int $chatId,
        int $actorUserId,
        string $text,
        array $attachments,
        array $speaker,
        array $thread,
        ?string $kind = null,
    ): void {
        $payload = ['thread' => $thread];
        if ($kind !== null) {
            $payload['kind'] = $kind;
        }

        $attachments[] = ['type' => 'chat_system', 'payload' => $payload];
        $attachments[] = ['type' => 'chat_speaker', 'payload' => $speaker];
        $this->chats->send($chatId, $actorUserId, $text, $attachments, null);
    }
}
