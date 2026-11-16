<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Exception\ChatNotFoundException;
use Mifrial\Messages\Chat\Interface\Service\IChats;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationResult;
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
     * Пишет отдельную строку траты заряда, если объявление уже было.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param array{id: string, parentId: string, kind: string}|null $thread Нить или null.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function chargeSpent(
        GameRecord $game,
        int $actorUserId,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        ?array $thread = null,
    ): void {
        if ($prepared->chargeSpellCode === null) {
            return;
        }

        $thread ??= GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $token = $this->tokens->getEntityToken($prepared->casterKind, $prepared->casterId);
        $this->send(
            $this->chatId($game),
            $actorUserId,
            $this->chargeSpentText($prepared, $token, '[[rule:' . $prepared->spellCode . ']]'),
            $this->attachments($thread, $prepared->casterKind, $prepared->casterId),
        );
    }

    /**
     * Текст траты заряда.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param string $token Токен кастера.
     * @param string $spell Токен заклинания.
     *
     * @return string Текст.
     */
    private function chargeSpentText(GameSpellCastPrepared $prepared, string $token, string $spell): string
    {
        $stateCode = $prepared->chargeSpend['stateCode'] ?? '';
        $name = $stateCode === ''
            ? 'заряд'
            : ($prepared->slice->findLive($stateCode)?->getName() ?? $stateCode);
        $holder = $prepared->chargeSpellCode === null ? '' : ' с [[rule:' . $prepared->chargeSpellCode . ']]';

        return $token . ' тратит ' . $name . $holder . ' и сотворяет ' . $spell
            . ' за ' . $prepared->spent . ' ОД' . $this->manaText($prepared) . '.';
    }

    /**
     * Хвост объявления про ману источника.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return string Текст или ''.
     */
    private function manaText(GameSpellCastPrepared $prepared): string
    {
        $cost = $prepared->manaSpend['cost'] ?? 0;
        if ($cost <= 0) {
            return '';
        }

        return ' и ' . $cost . ' маны';
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
     * @param GameSpellCastSaturationResult|null $saturation Итог насыщения.
     * @param array<int, array<string, mixed>> $nextCast Поставленные хвосты.
     * @param array<string, mixed>|null $deviation Отклонение.
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
        ?GameSpellCastSaturationResult $saturation = null,
        array $nextCast = [],
        ?array $deviation = null,
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
                $prepared->chargeSpellCode === null
                    ? $token . ' сотворяет ' . $spell . ' за ' . $prepared->spent . ' ОД' . $this->manaText($prepared) . '.'
                    : $this->chargeSpentText($prepared, $token, $spell),
                $attachments,
            );
        }

        $this->postUpgrades($chatId, $actorUserId, $token, $prepared, $attachments);

        $roll = $check['roll'] ?? null;
        if (($check['needsCheck'] ?? false) === true && is_array($roll)) {
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' проходит проверку на сотворение.',
                [['type' => 'roll', 'payload' => $roll], ...$attachments],
            );
        }

        if ($deviation !== null) {
            $this->postDeviation($chatId, $actorUserId, $token, $prepared, $deviation, $attachments);
        }

        if ($saturation !== null && $saturation->getSteps() > 0) {
            $source = $saturation->getSource();
            $label = $source === null ? 'энергонасыщение' : '[[rule:' . $source->getRuleCode() . ']]';
            $this->send(
                $chatId,
                $actorUserId,
                $token . ' применяет ' . $label . ': потрачено '
                    . $saturation->getRatingSpent() . ' РУ, Мощь +'
                    . $saturation->getPowerDelta() . '.',
                $attachments,
            );
        }

        if ($nextCast !== []) {
            $labels = [];
            foreach ($nextCast as $row) {
                $code = $row['sourceRuleCode'] ?? null;
                if (is_string($code) && $code !== '') {
                    $labels[] = '[[rule:' . $code . ']]';
                }
            }

            if ($labels !== []) {
                $this->send(
                    $chatId,
                    $actorUserId,
                    $token . ' снижает Сложность следующего сотворения: ' . implode(', ', $labels) . '.',
                    $attachments,
                );
            }
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
     * Строка о применённых улучшениях каста.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $token Токен кастера.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     */
    private function postUpgrades(
        int $chatId,
        int $actorUserId,
        string $token,
        GameSpellCastPrepared $prepared,
        array $attachments,
    ): void {
        if ($prepared->upgrades->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($prepared->upgrades->getApplied() as $upgrade) {
            $label = '[[rule:' . $upgrade->getRuleCode() . ']]';
            if ($upgrade->getRequiredPowerDelta() > 0) {
                $label .= ' × ' . $upgrade->getValue();
            }

            $rows[] = $label;
        }

        $this->send(
            $chatId,
            $actorUserId,
            $token . ' применяет улучшения: ' . implode(', ', $rows) . '.',
            $attachments,
        );
    }

    /**
     * Отклонение: бросок 2d6, сила и весть о взрыве.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $token Токен кастера.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $deviation Исход.
     * @param list<array<string, mixed>> $attachments Вложения.
     *
     * @return void
     */
    private function postDeviation(
        int $chatId,
        int $actorUserId,
        string $token,
        GameSpellCastPrepared $prepared,
        array $deviation,
        array $attachments,
    ): void {
        $faces = is_array($deviation['faces'] ?? null) ? array_values($deviation['faces']) : [];
        $dieFaces = is_int($deviation['dieFaces'] ?? null) ? $deviation['dieFaces'] : 6;
        $payload = [
            'spec' => [
                'diceCount' => count($faces),
                'dieSize' => 0,
                'dieFaces' => $dieFaces,
                'efficiency' => $dieFaces,
                'scoring' => 'face_sum',
                'advantages' => [],
                'label' => 'Магическое отклонение',
                'actorKey' => $prepared->casterKind . ':' . $prepared->casterId,
            ],
            'rolls' => $faces,
            'successes' => array_fill(0, count($faces), 0),
            'adjustedRolls' => $faces,
            'droppedRolls' => [],
            'totalSuccesses' => 0,
            'faceSum' => $deviation['faceSum'] ?? 0,
            'deviationStrength' => $deviation['strength'] ?? 0,
        ];

        $this->send(
            $chatId,
            $actorUserId,
            $token . ' получает магическое отклонение.',
            [['type' => 'roll', 'payload' => $payload], ...$attachments],
        );

        $strength = is_int($deviation['strength'] ?? null) ? $deviation['strength'] : 0;
        $this->send(
            $chatId,
            $actorUserId,
            $strength > 0
                ? $token . ' — магическое отклонение силой ' . $strength . '.'
                : $token . ' — магического отклонения нет.',
            $attachments,
        );

        if (($deviation['isDoubles'] ?? false) === true) {
            $this->send($chatId, $actorUserId, $token . ' — собранная магия взрывается.', $attachments);
        }
    }

    /**
     * Взрыв по целям: строка и последствия урона.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param GameBattleRecord $battle Бой.
     * @param array{id: string, parentId: string, kind: string}|null $thread Нить.
     * @param list<array<string, mixed>> $rows Строки целей.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function postBurst(
        GameRecord $game,
        int $actorUserId,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        ?array $thread,
        array $rows,
    ): void {
        if ($rows === []) {
            return;
        }

        $chatId = $this->chatId($game);
        $thread ??= GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $attachments = $this->attachments($thread, $prepared->casterKind, $prepared->casterId);
        foreach ($rows as $row) {
            $target = is_array($row['target'] ?? null) ? $row['target'] : null;
            if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
                continue;
            }

            $targetToken = $this->tokens->getEntityToken($target['type'], $target['id']);
            $damageName = is_string($row['damageName'] ?? null) ? $row['damageName'] : null;
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' попадает под взрыв.',
                [['type' => 'attack-calc', 'payload' => $this->burstCalc($row)], ...$attachments],
            );
            $consequence = is_array($row['consequence'] ?? null) ? $row['consequence'] : [];
            $this->postConsequence($chatId, $actorUserId, $targetToken, $prepared, $consequence, $attachments, $damageName);
        }
    }

    /**
     * Прыжок цепи: строка попадания, промах по сопротивлению или последствия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Автор.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $chain Состояние цепи.
     * @param array<string, mixed>|null $row Строка прыжка или null.
     * @param bool $broken Цепь оборвалась.
     * @param bool $avoided Цель избежала заклинания из-за Сопротивления магии.
     * @param int $spentRating Сколько успеха каста поглотило сопротивление.
     * @param int $ratingAfter Остаток успеха после прыжка.
     * @param array{type: string, id: int} $target Цель прыжка.
     *
     * @return void
     *
     * @throws GameInvalidException Если чата нет или отправитель не участник.
     */
    public function postChain(
        GameRecord $game,
        int $actorUserId,
        GameBattleRecord $battle,
        array $chain,
        ?array $row,
        bool $broken,
        bool $avoided,
        int $spentRating,
        int $ratingAfter,
        array $target,
    ): void {
        $chatId = $this->chatId($game);
        $thread = is_array($chain['thread'] ?? null)
            ? $chain['thread']
            : GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        $actor = is_array($chain['actor'] ?? null) ? $chain['actor'] : [];
        $attachments = $this->attachments(
            $thread,
            is_string($actor['type'] ?? null) ? $actor['type'] : 'character',
            is_int($actor['id'] ?? null) ? $actor['id'] : 0,
        );
        $spell = '[[rule:' . (string) $chain['ruleCode'] . ']]';
        $hit = is_array($row['target'] ?? null) ? $row['target'] : $target;
        $targetToken = $this->tokens->getEntityToken((string) $hit['type'], (int) $hit['id']);
        if ($row !== null) {
            $damageName = is_string($row['damageName'] ?? null) ? $row['damageName'] : null;
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' попадает под ' . $spell . '.',
                [['type' => 'attack-calc', 'payload' => $this->calc($row, 'Цепь', 'РУ цепи')], ...$attachments],
            );
            $consequence = is_array($row['consequence'] ?? null) ? $row['consequence'] : [];
            $this->postConsequence($chatId, $actorUserId, $targetToken, null, $consequence, $attachments, $damageName);
        }

        if ($avoided) {
            $this->send(
                $chatId,
                $actorUserId,
                $targetToken . ' гасит ' . $spell . ' Сопротивлением магии: сопротивление '
                    . $spentRating . ' РУ, остаток каста ' . ($ratingAfter + $spentRating)
                    . ' РУ; ' . $spell . ' обрывается.',
                $attachments,
            );

            return;
        }

        if ($spentRating > 0) {
            $this->send(
                $chatId,
                $actorUserId,
                'Сопротивление магии ' . $targetToken . ' забирает ' . $spentRating
                    . ' РУ каста; остаток ' . $ratingAfter . '.',
                $attachments,
            );
        }

        if (!$broken) {
            return;
        }

        if ($row === null) {
            $this->send(
                $chatId,
                $actorUserId,
                $spell . ' не долетает до ' . $targetToken . ': урон ниже минимума, цепь обрывается.',
                $attachments,
            );

            return;
        }

        $this->send($chatId, $actorUserId, $spell . ' обрывается.', $attachments);
    }

    /**
     * Payload attack-calc для цели взрыва: урон, сопротивление и последствия.
     *
     * @param array<string, mixed> $row Строка цели.
     *
     * @return array<string, mixed> Payload.
     */
    private function burstCalc(array $row): array
    {
        return $this->calc($row, 'Взрыв магии', 'РУ взрыва');
    }

    /**
     * Payload attack-calc с подписью источника удара.
     *
     * @param array<string, mixed> $row Строка цели.
     * @param string $heading Заголовок.
     * @param string $srCaption Подпись РУ.
     *
     * @return array<string, mixed> Payload.
     */
    private function calc(array $row, string $heading, string $srCaption): array
    {
        $consequence = is_array($row['consequence'] ?? null) ? $row['consequence'] : [];
        $injury = $this->dimensionalPair($row['injury'] ?? null);

        return [
            'raw' => $injury === null ? 0 : $injury['base'],
            'damage' => $this->dimensionalPair($row['damage'] ?? null) ?? ['base' => 0, 'size' => 0],
            'damageTypeName' => is_string($row['damageName'] ?? null) ? $row['damageName'] : '',
            'resistance' => $this->resistive($row['resistance'] ?? null),
            'penetration' => 0,
            'injury' => $injury,
            'endurance' => $this->dimensionalPair($row['endurance'] ?? null) ?? ['base' => 0, 'size' => 0],
            'defenseIgnored' => ($row['defenseIgnored'] ?? false) === true,
            'attackSrLabel' => (string) (is_int($row['successRating'] ?? null) ? $row['successRating'] : 0),
            'attackSrCaption' => $srCaption,
            'appliedSr' => null,
            'srCap' => null,
            'heading' => $heading,
            'stun' => null,
            'shock' => null,
            'exhaustion' => is_int($consequence['exhaustionDelta'] ?? null) ? $consequence['exhaustionDelta'] : 0,
            'remainingHpDamage' => $this->dimensionalPair($consequence['damageRemainder'] ?? null),
            'wound' => is_int($consequence['wound'] ?? null) ? $consequence['wound'] : null,
            'knockout' => ($consequence['unconscious'] ?? false) === true,
            'cuttingWound' => null,
            'layers' => [],
        ];
    }

    /**
     * Пара размерного числа.
     *
     * @param mixed $value Значение.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    private function dimensionalPair(mixed $value): ?array
    {
        if ($value instanceof DimensionalNumber) {
            return ['base' => $value->getBase(), 'size' => $value->getSize()];
        }

        if (is_array($value) && is_int($value['base'] ?? null) && is_int($value['size'] ?? null)) {
            return ['base' => $value['base'], 'size' => $value['size']];
        }

        return null;
    }

    /**
     * Число сопротивления из размерного числа.
     *
     * @param mixed $value Значение.
     *
     * @return int Число.
     */
    private function resistive(mixed $value): int
    {
        return $value instanceof DimensionalNumber ? $value->toInteger() : 0;
    }

    /**
     * Итог, состояния, Воля и увечье цели.
     *
     * @param int $chatId Чат.
     * @param int $actorUserId Автор.
     * @param string $targetToken Токен цели.
     * @param GameSpellCastPrepared|null $prepared Подготовка или null у прыжка цепи.
     * @param array<string, mixed> $consequence Последствия.
     * @param list<array<string, mixed>> $attachments Вложения.
     * @param string|null $damageName Родительный типа урона или null.
     *
     * @return void
     */
    private function postConsequence(
        int $chatId,
        int $actorUserId,
        string $targetToken,
        ?GameSpellCastPrepared $prepared,
        array $consequence,
        array $attachments,
        ?string $damageName = null,
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
            $payload = $this->tokens->getMaimPayload(
                $targetToken,
                $maim,
                $damageName ?? ($prepared === null ? '' : $this->damageTypeName($prepared)),
            );
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
