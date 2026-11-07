<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;

/**
 * Проверки до и после итога одиночного удара.
 */
final class GameStrikeSingleChecks
{
    /**
     * Создаёт проверки.
     *
     * @param GameStrikeChat $chat Токены и подписи.
     * @param GameStrikeSpend $spend Стоимость действия.
     * @param GameStrikeConsequences $consequences Публикация.
     * @param GameStrikeSingleView $view Подписи удара.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeChat $chat,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSingleView $view,
    ) {
    }

    /**
     * Проверки прикрытия или Силы до итога.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed>|null $cover Прикрытие.
     * @param array{winner: array<string, mixed>|null, failed: list<array<string, mixed>>} $split Части прикрытия.
     * @param array<string, mixed>|null $strengthRoll Проверка Силы.
     * @param string $reaction Реакция.
     *
     * @return array{preRollChecks: list<array<string, mixed>>, beforeOutcome: array<string, mixed>|null} Проверки.
     */
    public function before(array $header, ?array $cover, array $split, ?array $strengthRoll, string $reaction): array
    {
        if ($cover !== null) {
            return [
                'preRollChecks' => $this->coverChecks($header, $cover, $split['failed']),
                'beforeOutcome' => null,
            ];
        }

        return ['preRollChecks' => [], 'beforeOutcome' => $this->strength($header, $strengthRoll, $reaction)];
    }

    /**
     * Дополнительные проверки после итога: прирост состояний.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array<string, mixed> $open Открытый удар.
     * @param int $actorUserId Актор.
     * @param int $strikeId Удар.
     * @param list<array{roll: array<string, mixed>, text: string, verdict: string|null}> $checks Проверки.
     *
     * @return void
     */
    public function postChecks(
        GameRecord $game,
        GameBattleRecord $battle,
        array $open,
        int $actorUserId,
        int $strikeId,
        array $checks,
    ): void {
        $thread = $this->view->thread($battle, $strikeId);
        $defender = $this->view->defenderToken($open);
        $speaker = $this->view->defenderSpeaker($open);
        foreach ($checks as $entry) {
            $this->consequences->chat->postCheck($game, $actorUserId, $thread['threadId'], $defender . ' ' . $entry['text'], $entry['roll'], $speaker, $thread['parentId']);
            $verdict = is_string($entry['verdict'] ?? null) ? $entry['verdict'] : '';
            if ($verdict !== '') {
                $this->consequences->chat->postLines($game, $actorUserId, $thread['threadId'], [$defender . ' ' . $verdict], $speaker, $thread['parentId']);
            }
        }
    }

    /**
     * Проверки исходной цели и провалившихся прикрывающих.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $cover Прикрытие.
     * @param list<array<string, mixed>> $failed Провалившиеся попытки.
     *
     * @return list<array<string, mixed>> Проверки.
     */
    private function coverChecks(array $header, array $cover, array $failed): array
    {
        $checks = $this->targetCheck($header, $cover);
        foreach ($failed as $attempt) {
            $check = $this->attemptCheck($attempt);
            if ($check !== null) {
                $checks[] = $check;
            }
        }

        return $checks;
    }

    /**
     * Проверка реакции исходной цели.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed> $cover Прикрытие.
     *
     * @return list<array<string, mixed>> Проверки.
     */
    private function targetCheck(array $header, array $cover): array
    {
        $reaction = is_string($cover['targetReaction'] ?? null) ? $cover['targetReaction'] : 'ignore';
        $roll = is_array($cover['targetRoll'] ?? null) ? $cover['targetRoll'] : null;
        if (($cover['targetActual'] ?? false) === true || $reaction === 'ignore' || $roll === null) {
            return [];
        }

        $ruleCode = is_string($cover['targetReactionRuleCode'] ?? null) ? $cover['targetReactionRuleCode'] : null;
        $ap = $this->spend->getActionAp($header['slice'], $ruleCode ?? '');

        return [[
            'text' => $this->chat->getReactionBit($header['victim'], $reaction, $ruleCode, $ap) . '.',
            'roll' => $roll,
        ]];
    }

    /**
     * Проверка одной попытки прикрытия.
     *
     * @param array<string, mixed> $attempt Попытка.
     *
     * @return array<string, mixed>|null Проверка или null.
     */
    private function attemptCheck(array $attempt): ?array
    {
        $roll = $attempt['roll'] ?? null;
        $actor = $attempt['actor'] ?? null;
        if (!is_array($roll) || !is_array($actor)) {
            return null;
        }

        if (!is_string($actor['type'] ?? null) || !is_int($actor['id'] ?? null)) {
            return null;
        }

        $reaction = is_string($attempt['reaction'] ?? null) ? $attempt['reaction'] : '';
        $cost = is_int($attempt['cost'] ?? null) ? $attempt['cost'] : 0;
        $block = is_string($attempt['blockItemRuleCode'] ?? null) && $attempt['blockItemRuleCode'] !== ''
            ? ' с [[rule:' . $attempt['blockItemRuleCode'] . ']]'
            : '';

        return [
            'text' => $this->chat->getEntityToken($actor['type'], $actor['id'])
                . ' пытается совершить [[rule:' . $reaction . ']] за ' . $cost . ' ОД' . $block . '.',
            'roll' => $roll,
        ];
    }

    /**
     * Проверка Силы перед итогом.
     *
     * @param array<string, mixed> $header Подписи.
     * @param array<string, mixed>|null $strengthRoll Проверка Силы.
     * @param string $reaction Реакция.
     *
     * @return array<string, mixed>|null Проверка или null.
     */
    private function strength(array $header, ?array $strengthRoll, string $reaction): ?array
    {
        if ($strengthRoll === null) {
            return null;
        }

        $label = is_string($strengthRoll['label'] ?? null) && $strengthRoll['label'] !== ''
            ? $strengthRoll['label']
            : 'проверка';
        $dodgeFailed = $reaction === 'dodge';
        $prefix = $dodgeFailed ? $header['defender'] . ' не успевает уклониться — ' : '';
        $adverb = $dodgeFailed ? 'беспрепятственно ' : '';

        return [
            'text' => $prefix . $header['attacker'] . ' ' . $adverb . 'совершает проверку «' . $label . '».',
            'roll' => $strengthRoll,
        ];
    }
}
