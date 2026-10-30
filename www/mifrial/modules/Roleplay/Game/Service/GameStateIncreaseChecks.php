<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Проверка прироста состояния: бросок из спеки и операция исхода.
 *
 * Вход — отчёт Character: что применилось и сколько стало. Правило подавления
 * и строки листа остаются в Character, здесь только кубик и исход.
 */
final class GameStateIncreaseChecks
{
    /**
     * Создаёт исполнитель проверок прироста.
     *
     * @param GameStrikeRules $rules Срез правил.
     * @param GameCheckRoll $checks Бросок.
     * @param GameStateIncreaseRule $rule Связка состояние-проверка-исход.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameCheckRoll $checks,
        private readonly GameStateIncreaseRule $rule,
    ) {
    }

    /**
     * Дописывает исходы проверок и отдаёт броски.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист до записи.
     * @param int $rulesRevision Ревизия листа.
     * @param array<string, array{before: int, after: int, check_code: string|null}> $states Отчёт Character.
     * @param list<array<string, mixed>> $operations Операции.
     *
     * @return array{
     *     operations: list<array<string, mixed>>,
     *     rolls: list<array{roll: array<string, mixed>, text: string}>
     * } Операции и броски.
     *
     * @throws GameInvalidException Если связка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    public function plan(
        GameRecord $game,
        array $sheet,
        int $rulesRevision,
        array $states,
        array $operations,
    ): array {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $rulesRevision);
        $rolls = [];
        foreach ($states as $code => $change) {
            if (!is_string($code) || !is_array($change)) {
                continue;
            }

            $before = $change['before'] ?? null;
            $after = $change['after'] ?? null;
            $checkCode = $change['check_code'] ?? null;
            if (!is_int($before) || !is_int($after) || $after <= $before) {
                continue;
            }

            if (!is_string($checkCode) || $checkCode === '') {
                continue;
            }

            $check = $this->rule->check($slice, $code, $checkCode);
            $roll = $this->checks->throwWithDifficulty(
                $game->getSpaceId(),
                $rulesRevision,
                $checkCode,
                $sheet,
                ['base' => $after, 'size' => 0],
            );
            $rating = is_int($roll['rating'] ?? null) ? $roll['rating'] : 0;
            $outcome = $this->rule->outcome($slice, $check, $rating);
            if ($outcome !== null) {
                $operations[] = ['kind' => 'putState', 'stateRuleCode' => $outcome];
            }

            $rolls[] = ['roll' => $roll, 'text' => $this->text($slice, $code, $roll, $after)];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Текст сообщения броска.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код состояния.
     * @param array<string, mixed> $roll Бросок.
     * @param int $value Значение после прироста.
     *
     * @return string Текст.
     */
    private function text(CharacterRuleSlice $slice, string $code, array $roll, int $value): string
    {
        $label = is_string($roll['label'] ?? null) && $roll['label'] !== ''
            ? $roll['label']
            : 'проверка';
        $name = $slice->findLive($code)?->getName() ?? $code;

        return 'проходит проверку «' . $label . '» против «' . $name . '» ' . $value . '.';
    }
}
