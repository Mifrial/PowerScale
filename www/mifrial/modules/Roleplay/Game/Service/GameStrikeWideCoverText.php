<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Тексты прикрытия и проверки прикрывающих в чате широкого удара.
 */
final class GameStrikeWideCoverText
{
    /**
     * Создаёт тексты прикрытия.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSpend $spend Стоимость действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSpend $spend,
    ) {
    }

    /**
     * Строка победившего прикрытия.
     *
     * @param array<string, mixed> $cover Описание прикрытия.
     *
     * @return string Текст или пустая строка.
     */
    public function bit(array $cover): string
    {
        foreach (is_array($cover['attempts'] ?? null) ? $cover['attempts'] : [] as $attempt) {
            if (!is_array($attempt) || ($attempt['winner'] ?? false) !== true) {
                continue;
            }

            $actor = $attempt['actor'] ?? null;
            if (!is_array($actor) || !is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
                continue;
            }

            $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
            $block = $this->block($attempt['blockItemRuleCode'] ?? null);

            return $this->chat->getEntityToken($actor['type'], $actor['id'])
                . ' принимает удар на себя: ' . $this->ruleToken($attempt['ruleCode'] ?? null)
                . ' за ' . $cost . ' ОД' . $block;
        }

        return '';
    }

    /**
     * Строка провалившихся попыток прикрытия.
     *
     * @param array<string, mixed> $cover Описание прикрытия.
     *
     * @return string Текст или пустая строка.
     */
    public function attempts(array $cover): string
    {
        $target = is_array($cover['target'] ?? null) ? $cover['target'] : null;
        if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
            return '';
        }

        $victim = $this->chat->getEntityToken($target['type'], $target['id']);
        $parts = [];
        foreach (is_array($cover['attempts'] ?? null) ? $cover['attempts'] : [] as $attempt) {
            $line = is_array($attempt) ? $this->attemptLine($attempt, $victim) : null;
            if ($line !== null) {
                $parts[] = $line;
            }
        }

        return implode('; ', $parts);
    }

    /**
     * Проверки исходной цели и прикрывающих до общего броска.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $cover Описание прикрытия.
     * @param array<string, mixed>|null $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    public function postChecks(
        GameRecord $game,
        int $actorUserId,
        string $threadId,
        CharacterRuleSlice $slice,
        array $cover,
        ?array $speaker,
        ?string $parentId,
    ): void {
        $this->targetCheck($game, $actorUserId, $threadId, $slice, $cover, $speaker, $parentId);
        foreach (is_array($cover['attempts'] ?? null) ? $cover['attempts'] : [] as $attempt) {
            if (is_array($attempt)) {
                $this->attemptCheck($game, $actorUserId, $threadId, $attempt, $speaker, $parentId);
            }
        }
    }

    /**
     * Проверка реакции исходной цели.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $cover Описание прикрытия.
     * @param array<string, mixed>|null $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    private function targetCheck(
        GameRecord $game, int $actorUserId, string $threadId, CharacterRuleSlice $slice,
        array $cover, ?array $speaker, ?string $parentId,
    ): void {
        $part = $this->targetPart($cover);
        if ($part === null) {
            return;
        }

        $victim = $this->chat->getEntityToken($part['target']['type'], $part['target']['id']);
        $code = is_string($cover['targetReactionRuleCode'] ?? null) ? $cover['targetReactionRuleCode'] : null;
        $this->consequences->chat->postCheck(
            $game,
            $actorUserId,
            $threadId,
            $this->chat->getReactionBit($victim, $part['reaction'], $code, $this->spend->getActionAp($slice, $code ?? '')) . '.',
            $part['roll'],
            $speaker,
            $parentId,
        );
    }

    /**
     * Цель, реакция и бросок проверки исходной цели.
     *
     * @param array<string, mixed> $cover Описание прикрытия.
     *
     * @return array{target: array<string, mixed>, reaction: string, roll: array<string, mixed>}|null Данные.
     */
    private function targetPart(array $cover): ?array
    {
        if (($cover['targetActual'] ?? false) === true) {
            return null;
        }

        $target = is_array($cover['target'] ?? null) ? $cover['target'] : null;
        if ($target === null || !is_string($target['type'] ?? null) || !is_int($target['id'] ?? null)) {
            return null;
        }

        $reaction = is_string($cover['targetReaction'] ?? null) ? $cover['targetReaction'] : 'ignore';
        $roll = is_array($cover['targetRoll'] ?? null) ? $cover['targetRoll'] : null;

        return ($reaction === 'ignore' || $roll === null)
            ? null
            : ['target' => $target, 'reaction' => $reaction, 'roll' => $roll];
    }

    /**
     * Проверка одной попытки прикрытия.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Актор.
     * @param string $threadId Тред.
     * @param array<string, mixed> $attempt Попытка.
     * @param array<string, mixed>|null $speaker Спикер.
     * @param string|null $parentId Родитель.
     *
     * @return void
     */
    private function attemptCheck(
        GameRecord $game, int $actorUserId, string $threadId, array $attempt, ?array $speaker, ?string $parentId,
    ): void {
        $roll = $attempt['roll'] ?? null;
        $actor = $attempt['actor'] ?? null;
        if (!is_array($roll) || !is_array($actor)) {
            return;
        }

        if (!is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
            return;
        }

        $reaction = is_string($attempt['reaction'] ?? null) ? $attempt['reaction'] : '';
        $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
        $this->consequences->chat->postCheck(
            $game,
            $actorUserId,
            $threadId,
            $this->chat->getEntityToken($actor['type'], $actor['id'])
                . ' пытается совершить [[rule:' . $reaction . ']] за ' . $cost . ' ОД'
                . $this->block($attempt['blockItemRuleCode'] ?? null) . '.',
            $roll,
            $speaker,
            $parentId,
        );
    }

    /**
     * Строка провалившейся попытки прикрытия.
     *
     * @param array<string, mixed> $attempt Попытка.
     * @param string $victim Цель.
     *
     * @return string|null Текст или null.
     */
    private function attemptLine(array $attempt, string $victim): ?string
    {
        if (($attempt['winner'] ?? false) === true) {
            return null;
        }

        $actor = $attempt['actor'] ?? null;
        if (!is_array($actor) || !is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
            return null;
        }

        $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
        $reaction = is_string($attempt['reaction'] ?? null) && $attempt['reaction'] !== ''
            ? ', [[rule:' . $attempt['reaction'] . ']]'
            : '';

        return $this->chat->getEntityToken($actor['type'], $actor['id'])
            . ' пытается прикрыть ' . $victim . ': ' . $this->ruleToken($attempt['ruleCode'] ?? null)
            . ' за ' . $cost . ' ОД' . $reaction . $this->block($attempt['blockItemRuleCode'] ?? null);
    }

    /**
     * Хвост оружия блока.
     *
     * @param mixed $ruleCode Код предмета.
     *
     * @return string Текст или пустая строка.
     */
    private function block(mixed $ruleCode): string
    {
        return is_string($ruleCode) && $ruleCode !== '' ? ' с [[rule:' . $ruleCode . ']]' : '';
    }

    /**
     * Токен карточки или запасное имя.
     *
     * @param mixed $ruleCode Код.
     *
     * @return string Токен.
     */
    private function ruleToken(mixed $ruleCode): string
    {
        return is_string($ruleCode) && $ruleCode !== ''
            ? '[[rule:' . $ruleCode . ']]'
            : 'Прикрытие';
    }
}
