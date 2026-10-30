<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Проверки прироста состояний: бросок и применение исхода.
 *
 * Вход — отчёт Character: что применилось и сколько стало. Правило подавления
 * и строки листа остаются в Character, здесь только кубик и исход. Исход
 * либо кладёт состояние, либо двигает шкалу decline.
 */
final class GameStateIncreaseChecks
{
    /**
     * Создаёт исполнитель проверок прироста.
     *
     * @param GameStrikeRules $rules Срез правил.
     * @param GameStateCheck $check Общая проверка прироста.
     * @param GameStateDecline $decline Шкала decline.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStateCheck $check,
        private readonly GameStateDecline $decline,
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
     * @param list<string>|null $onlyCodes Только эти состояния или null.
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
        ?array $onlyCodes = null,
    ): array {
        $slice = $this->rules->ruleSlice($game->getSpaceId(), $rulesRevision);
        $rolls = [];
        foreach ($states as $code => $change) {
            if (!is_string($code) || !is_array($change)) {
                continue;
            }

            if ($onlyCodes !== null && !in_array($code, $onlyCodes, true)) {
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

            $check = $this->check->check($slice, $code, $checkCode);
            $codes = $this->decline->codes($slice, $check);
            $decline = $this->isDecline($slice, $codes);
            $current = $decline ? $this->decline->current($slice, $sheet, $check) : null;
            if ($decline && $codes !== [] && ($current ?? 0) >= count($codes)) {
                continue;
            }

            $rolled = $this->check->roll($game, $sheet, $slice, $code, $checkCode, $after);
            $outcome = $rolled['outcome'];
            if ($outcome !== null) {
                if ($decline) {
                    $rank = $this->decline->rankInCheck($check, $outcome);
                    $next = $rank === null ? null : ['code' => $outcome, 'rank' => $rank];
                    $operation = $this->decline->change(true, $current, $next);
                    foreach ($this->decline->operations($sheet, $slice, $operation, $codes) as $row) {
                        $operations[] = $row;
                    }
                } else {
                    $operations[] = ['kind' => 'putState', 'stateRuleCode' => $outcome];
                }
            }

            $rolls[] = ['roll' => $rolled['roll'], 'text' => $this->text($slice, $code, $rolled['roll'], $after)];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Исходы проверки двигают шкалу decline.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param list<string> $codes Коды исходов.
     *
     * @return bool true, если хотя бы один исход — decline.
     */
    private function isDecline(CharacterRuleSlice $slice, array $codes): bool
    {
        foreach ($codes as $code) {
            if ($this->decline->rankOf($slice, $code) !== null) {
                return true;
            }
        }

        return false;
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
