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
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Hit\AttackHit;
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
     * @param bool $announce Писать ли строку объявления.
     * @param array{id: string, parentId: string, kind: string}|null $thread Нить удара касания; null — тред хода.
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
        bool $announce = true,
        ?array $thread = null,
    ): void {
        $chatId = $this->chatId($game);
        $touch = $prepared->spell->getBase()->getHitResolution() instanceof AttackHit;
        $thread ??= GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $attachments = $this->attachments($thread, $prepared->casterKind, $prepared->casterId);
        $token = $this->tokens->getEntityToken($prepared->casterKind, $prepared->casterId);
        $spell = '[[rule:' . $prepared->spellCode . ']]';
        $target = $prepared->damageTarget ?? $prepared->target;
        $targetToken = $target === null
            ? ''
            : $this->tokens->getEntityToken($target['type'], $target['id']);

        if ($announce) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' сотворяет ' . $spell . ' за ' . $prepared->spent . ' ОД.',
                $attachments,
            );
        }

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
        if (($damage['milk'] ?? false) === true) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' успешно сотворяет ' . $spell . ', но касание уходит в воздух.',
                $attachments,
            );

            return;
        }

        if ($touch) {
            $this->send($chatId, $actorUserId, $token . ' успешно сотворяет ' . $spell . '.', $attachments);
        }

        if ($injury instanceof DimensionalNumber) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' поражает ' . $targetToken . ' на '
                    . $this->tokens->getDimensionalText([
                        'base' => $injury->getBase(),
                        'size' => $injury->getSize(),
                    ])
                    . ' '
                    . $this->plural($injury->getBase(), 'повреждение', 'повреждения', 'повреждений')
                    . '.',
                $attachments,
            );
        }

        $consequence = $damage['consequence'] ?? [];
        if (is_array($consequence)) {
            $this->postConsequence($chatId, $actorUserId, $targetToken, $prepared, $consequence, $attachments);
        }
    }

    /**
     * Итог, состояния, Воля и увечье цели.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $targetToken Токен цели.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $consequence Последствия.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     */
    private function postConsequence(
        int $chatId,
        int $actorUserId,
        string $targetToken,
        GameSpellCastPrepared $prepared,
        array $consequence,
        array $attachments,
    ): void {
        $summary = $this->consequenceSummary($targetToken, $consequence);
        if ($summary !== '') {
            $this->send($chatId, $actorUserId, $summary, $attachments);
        }

        foreach ($consequence['states'] ?? [] as $state) {
            if (!is_array($state) || !is_string($state['code'] ?? null) || $state['code'] === '') {
                continue;
            }

            $value = $state['value'] ?? null;
            $valueText = is_int($value) ? (string) $value : $this->tokens->getDimensionalText($value);
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' получает [[rule:' . $state['code'] . ']] силой ' . $valueText . '.',
                $attachments,
            );
        }

        $will = $consequence['will'] ?? null;
        if (is_array($will)) {
            $outcome = is_string($consequence['willOutcome'] ?? null) ? $consequence['willOutcome'] : 'clear';
            $rating = is_int($will['rating'] ?? null) ? $will['rating'] : 0;
            $this->send($chatId, $actorUserId, '', [['type' => 'roll', 'payload' => $will], ...$attachments]);
            $this->send(
                $chatId,
                $actorUserId,
                $this->tokens->getExhaustionText($targetToken, $rating, $outcome),
                $attachments,
            );
        }

        $maim = $consequence['maim'] ?? null;
        if (is_array($maim)) {
            $payload = $this->tokens->getMaimPayload($targetToken, $maim, $this->damageTypeName($prepared));
            $this->send($chatId, $actorUserId, '', [['type' => 'roll', 'payload' => $maim], ...$attachments]);
            $this->send(
                $chatId,
                $actorUserId,
                $payload['text'],
                [['type' => 'injury-details', 'payload' => $payload['details']], ...$attachments],
            );
        }
    }

    /**
     * Итоговая строка: истощение, остаток повреждений и сложности проверок.
     *
     * @param string $targetToken Токен цели.
     * @param array<string, mixed> $consequence Последствия.
     *
     * @return string Текст или пустая строка.
     */
    private function consequenceSummary(string $targetToken, array $consequence): string
    {
        $delta = is_int($consequence['exhaustionDelta'] ?? null) ? $consequence['exhaustionDelta'] : 0;
        $total = is_int($consequence['exhaustionTotal'] ?? null) ? $consequence['exhaustionTotal'] : $delta;
        $remainder = is_array($consequence['damageRemainder'] ?? null) ? $consequence['damageRemainder'] : null;
        $remaining = 0;
        $remainingText = '0';
        if (is_array($remainder) && is_int($remainder['base'] ?? null) && is_int($remainder['size'] ?? null)) {
            $remaining = $remainder['base'];
            $remainingText = $this->tokens->getDimensionalText($remainder);
        }

        $totals = [];
        if ($total > 0) {
            $totals[] = $total . ' ' . $this->plural($total, 'истощение', 'истощения', 'истощений');
        }

        if ($remaining > 0) {
            $damage = $this->plural(
                $remaining,
                'нераспределённое повреждение',
                'нераспределённых повреждения',
                'нераспределённых повреждений',
            );
            $totals[] = $remainingText . ' ' . $damage;
        }

        if ($delta === 0 && $remaining === 0) {
            return '';
        }

        if ($delta > 0) {
            $text = $targetToken . ' получает ' . $delta . ' '
                . $this->plural($delta, 'истощение', 'истощения', 'истощений');
            $text .= ', итого ' . implode(' и ', $totals) . '.';
        } else {
            $text = $targetToken . ' получает ' . implode(' и ', $totals) . '.';
        }

        $checks = [];
        $will = $consequence['will'] ?? null;
        if (is_array($will)) {
            $difficulty = is_array($will['difficulty'] ?? null) && is_int($will['difficulty']['base'] ?? null)
                ? $will['difficulty']['base']
                : null;
            if ($difficulty !== null) {
                $checks[] = 'проверку на истощение со сложностью ' . $difficulty;
            }
        }

        $maim = $consequence['maim'] ?? null;
        if (is_array($maim)) {
            $difficulty = is_array($maim['difficulty'] ?? null) && is_int($maim['difficulty']['base'] ?? null)
                ? $maim['difficulty']['base']
                : null;
            if ($difficulty !== null) {
                $checks[] = 'проверку на получение увечья со сложностью ' . $difficulty;
            }
        }

        if ($checks !== []) {
            $text .= ' ' . $targetToken . ' вынужден пройти ' . $this->joinAnd($checks) . '!';
        }

        return $text;
    }

    /**
     * Страдательный залог/род не задан: перечисление через «и».
     *
     * @param list<string> $parts Части.
     *
     * @return string Текст.
     */
    private function joinAnd(array $parts): string
    {
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        return implode(', ', array_slice($parts, 0, -1)) . ' и ' . $parts[count($parts) - 1];
    }

    /**
     * Русское склонение по числу.
     *
     * @param int $count Число.
     * @param string $one Форма для 1.
     * @param string $few Форма для 2–4.
     * @param string $many Форма для 5+.
     *
     * @return string Форма.
     */
    private function plural(int $count, string $one, string $few, string $many): string
    {
        $mod100 = $count % 100;
        if ($mod100 >= 11 && $mod100 <= 14) {
            return $many;
        }

        return match ($count % 10) {
            1 => $one,
            2, 3, 4 => $few,
            default => $many,
        };
    }

    /**
     * Вид урона заклинания для подробностей увечья.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return string Вид урона.
     */
    private function damageTypeName(GameSpellCastPrepared $prepared): string
    {
        $code = $prepared->spell->getSpell()->getDamage()?->getDamageTypeCode();
        if (!is_string($code) || $code === '') {
            return '';
        }

        $name = $prepared->slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? mb_strtolower($name) : '';
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
