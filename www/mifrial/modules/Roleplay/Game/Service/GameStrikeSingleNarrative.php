<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Нарратив одиночного удара.
 */
final class GameStrikeSingleNarrative
{
    /**
     * Создаёт нарратив.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSpend $spend Стоимость действия.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSpend $spend,
    ) {
    }

    /**
     * Собирает нарратив удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $result Итог.
     * @param string $reaction Реакция.
     * @param array<string, mixed>|null $cover Прикрытие.
     * @param array{winner: array<string, mixed>|null, failed: list<array<string, mixed>>} $split Части прикрытия.
     * @param array<string, mixed>|null $maneuver Раскрытый манёвр.
     *
     * @return string Нарратив.
     */
    public function of(
        GameRecord $game,
        array $header,
        array $open,
        array $result,
        string $reaction,
        ?array $cover,
        array $split,
        ?array $maneuver,
    ): string {
        $narrative = $this->base($header);
        $narrative .= $this->after($game, $header, $open, $split, $reaction, $cover !== null);
        $narrative .= $this->maneuverPart($maneuver);
        $narrative .= $this->rangedPart($open, $result);

        return $narrative;
    }

    /**
     * Начало нарратива: удар или толчок.
     *
     * @param array<string, mixed> $header Подписи.
     *
     * @return string Начало.
     */
    private function base(array $header): string
    {
        if ($header['push'] !== null) {
            return $header['attacker'] . ' толкает ' . $header['victim'] . '.';
        }

        return $header['attacker'] . ' наносит ' . $header['typeBit'] . ' ' . $header['kind']
            . ' оружием ' . $header['weapon'] . ' по ' . $header['victim'] . '.';
    }

    /**
     * Хвост нарратива: прикрытие, реакция, срыв толчка.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $open Открытый удар.
     * @param array{winner: array<string, mixed>|null, failed: list<array<string, mixed>>} $split Части прикрытия.
     * @param string $reaction Реакция.
     * @param bool $covered Есть ли прикрытие.
     *
     * @return string Хвост.
     */
    private function after(
        GameRecord $game,
        array $header,
        array $open,
        array $split,
        string $reaction,
        bool $covered,
    ): string {
        $coverPart = $this->coverPart($header, $split);
        if ($coverPart !== null) {
            return $coverPart;
        }

        $push = $header['push'];
        if ($push !== null && ($push['canceled'] ?? false) === true) {
            return ' Тот уклоняется — толчок сорван.';
        }

        return $this->reaction($game, $header, $open, $reaction, $covered);
    }

    /**
     * Прикрытие в нарративе: победитель или провалившиеся.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array{winner: array<string, mixed>|null, failed: list<array<string, mixed>>} $split Части прикрытия.
     *
     * @return string|null Текст или null.
     */
    private function coverPart(array $header, array $split): ?string
    {
        if ($split['winner'] !== null) {
            return $this->winner($header, $split['winner']);
        }

        if ($split['failed'] !== []) {
            return ' ' . $this->failed($split['failed'], $header['victim']) . ' — прикрытие не удаётся.';
        }

        return null;
    }

    /**
     * Прикрывающий принимает удар.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $attempt Попытка.
     *
     * @return string Текст.
     */
    private function winner(array $header, array $attempt): string
    {
        $rule = is_string($attempt['ruleCode'] ?? null) && $attempt['ruleCode'] !== ''
            ? '[[rule:' . $attempt['ruleCode'] . ']]'
            : 'Прикрытие';
        $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
        $block = is_string($attempt['blockItemRuleCode'] ?? null) && $attempt['blockItemRuleCode'] !== ''
            ? ' с [[rule:' . $attempt['blockItemRuleCode'] . ']]'
            : '';

        return ' ' . $header['defender'] . ' принимает удар на себя: '
            . $rule . ' за ' . $cost . ' ОД' . $block . '.';
    }

    /**
     * Строка провалившихся попыток прикрытия.
     *
     * @param list<array<string, mixed>> $attempts Попытки.
     * @param string $victim Исходная цель.
     *
     * @return string Текст.
     */
    private function failed(array $attempts, string $victim): string
    {
        $parts = [];
        foreach ($attempts as $attempt) {
            $actor = $attempt['actor'] ?? null;
            if (!is_array($actor) || !is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
                continue;
            }

            $rule = is_string($attempt['ruleCode'] ?? null) && $attempt['ruleCode'] !== ''
                ? '[[rule:' . $attempt['ruleCode'] . ']]'
                : 'Прикрытие';
            $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
            $reaction = is_string($attempt['reaction'] ?? null) && $attempt['reaction'] !== ''
                ? ', [[rule:' . $attempt['reaction'] . ']]'
                : '';
            $block = is_string($attempt['blockItemRuleCode'] ?? null) && $attempt['blockItemRuleCode'] !== ''
                ? ' с [[rule:' . $attempt['blockItemRuleCode'] . ']]'
                : '';
            $parts[] = $this->chat->getEntityToken($actor['type'], $actor['id'])
                . ' пытается прикрыть ' . $victim . ': ' . $rule . ' за ' . $cost . ' ОД'
                . $reaction . $block;
        }

        return implode('; ', $parts);
    }

    /**
     * Реакция цели или её отсутствие.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $open Открытый удар.
     * @param string $reaction Реакция.
     * @param bool $covered Есть ли прикрытие.
     *
     * @return string Текст.
     */
    private function reaction(GameRecord $game, array $header, array $open, string $reaction, bool $covered): string
    {
        if ($covered) {
            return '';
        }

        if ($reaction === 'ignore') {
            return ' Тот не реагирует.';
        }

        $reactionCode = $this->rules->cards->reactionRuleCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $reaction,
        );
        $turn = (GameStrikeTargetRow::conditions($open)['turn'] ?? false) === true;
        $turnCode = $turn
            ? $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), 'turn')
            : null;

        return ' ' . $this->chat->getReactionBit(
            'Тот',
            $reaction,
            $reactionCode,
            $this->spend->getActionAp($header['slice'], $reactionCode ?? ''),
            $turn,
            $this->spend->getActionAp($header['slice'], $turnCode ?? ''),
        ) . '!';
    }

    /**
     * Раскрытый манёвр.
     *
     * @param array<string, mixed>|null $maneuver Манёвр.
     *
     * @return string Текст.
     */
    private function maneuverPart(?array $maneuver): string
    {
        if ($maneuver === null) {
            return '';
        }

        $code = is_string($maneuver['ruleCode'] ?? null) ? $maneuver['ruleCode'] : '';
        $rule = $code !== '' ? '[[rule:' . $code . ']]' : 'Обманный манёвр';

        return ' Это был ' . $rule . ': атака проваливается.';
    }

    /**
     * Условия дальнего удара.
     *
     * @param array<string, mixed> $open Открытый удар.
     * @param array<string, mixed> $result Итог.
     *
     * @return string Текст или пустая строка.
     */
    private function rangedPart(array $open, array $result): string
    {
        if (($open['profile_type'] ?? null) !== 'shoot' && ($open['profile_type'] ?? null) !== 'throw') {
            return '';
        }

        $text = $this->chat->getRangedConditionsText(
            is_array($result['attackerRoll'] ?? null) ? $result['attackerRoll'] : [],
        );

        return $text === null ? '' : ' ' . $text;
    }
}
