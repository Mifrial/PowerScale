<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

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
     *     rolls: list<array{roll: array<string, mixed>, text: string, verdict: string|null}>
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
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $rulesRevision);
        $rolls = [];
        foreach ($states as $code => $change) {
            if ($onlyCodes !== null && !in_array($code, $onlyCodes, true)) {
                continue;
            }

            $planned = $this->planOne($game, $sheet, $slice, $code, $change);
            if ($planned === null) {
                continue;
            }

            foreach ($planned['operations'] as $row) {
                $operations[] = $row;
            }

            $rolls[] = $planned['roll'];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Один прирост: отсев, бросок и исход.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код состояния.
     * @param array{before: int, after: int, check_code: string|null} $change Прирост.
     *
     * @return array{operations: list<array<string, mixed>>, roll: array<string, mixed>}|null План или null.
     *
     * @throws GameInvalidException Если связка битая.
     * @throws GameNotFoundException Если ревизии нет.
     */
    private function planOne(
        GameRecord $game,
        array $sheet,
        CharacterRuleSlice $slice,
        string $code,
        array $change,
    ): ?array {
        $context = $this->contextOf($slice, $sheet, $code, $change);
        if ($context === null) {
            return null;
        }

        $after = $change['after'];
        $rolled = $this->check->roll($game, $sheet, $slice, $code, $context['checkCode'], $after);
        [$applied, $added] = $this->applyOutcome(
            $sheet,
            $slice,
            $context['check'],
            $context['decline'],
            $context['codes'],
            $context['current'],
            $rolled['outcome'],
        );

        return [
            'operations' => $added,
            'roll' => $this->rollEntry($slice, $code, $rolled, $after, $applied),
        ];
    }

    /**
     * Сообщение одного броска.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код состояния.
     * @param array{roll: array<string, mixed>, outcome: string|null} $rolled Бросок.
     * @param int $value Значение после прироста.
     * @param bool $applied Исход применён.
     *
     * @return array<string, mixed> Строка броска.
     */
    private function rollEntry(
        CharacterRuleSlice $slice,
        string $code,
        array $rolled,
        int $value,
        bool $applied,
    ): array {
        return [
            'roll' => $rolled['roll'],
            'text' => $this->text($slice, $code, $rolled['roll'], $value),
            'verdict' => $applied ? $this->verdict($slice, $rolled['outcome']) : null,
        ];
    }

    /**
     * Проверка и текущий ранг или null, если прирост не проверяется.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код состояния.
     * @param array{before: int, after: int, check_code: string|null} $change Прирост.
     *
     * @return array{checkCode: string, check: CheckSpec, codes: list<string>, decline: bool, current: int|null}|null Контекст.
     *
     * @throws GameInvalidException Если связка битая.
     */
    private function contextOf(
        CharacterRuleSlice $slice,
        array $sheet,
        string $code,
        array $change,
    ): ?array {
        $checkCode = $change['check_code'] ?? null;
        if ($change['after'] <= $change['before'] || $checkCode === null || $checkCode === '') {
            return null;
        }

        $check = $this->check->check($slice, $code, $checkCode);
        $codes = $this->decline->codes($slice, $check);
        $decline = $this->isDecline($slice, $codes);
        $current = $decline ? $this->decline->current($slice, $sheet, $check) : null;
        if ($decline && $codes !== [] && ($current ?? 0) >= count($codes)) {
            return null;
        }

        return [
            'checkCode' => $checkCode,
            'check' => $check,
            'codes' => $codes,
            'decline' => $decline,
            'current' => $current,
        ];
    }

    /**
     * Применяет исход проверки к листу.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Проверка.
     * @param bool $decline Исходы двигают шкалу.
     * @param list<string> $codes Коды исходов.
     * @param int|null $current Текущий ранг.
     * @param string|null $outcome Исход.
     *
     * @return array{0: bool, 1: list<array<string, mixed>>} Применён и операции.
     */
    private function applyOutcome(
        array $sheet,
        CharacterRuleSlice $slice,
        CheckSpec $check,
        bool $decline,
        array $codes,
        ?int $current,
        ?string $outcome,
    ): array {
        if ($outcome === null) {
            return [false, []];
        }

        if (!$decline) {
            return [true, [['kind' => 'putState', 'stateRuleCode' => $outcome]]];
        }

        $rank = $this->decline->rankInCheck($check, $outcome);
        $next = $rank === null ? null : ['code' => $outcome, 'rank' => $rank];
        $operation = $this->decline->change(true, $current, $next);

        return [$operation !== null, $this->decline->operations($sheet, $slice, $operation, $codes)];
    }

    /**
     * Строка исхода состояния.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код состояния.
     *
     * @return string Текст без имени цели.
     */
    private function verdict(CharacterRuleSlice $slice, string $code): string
    {
        $rule = $slice->findLive($code);
        if ($rule === null) {
            return 'получает состояние «' . $code . '».';
        }

        $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
        if ($spec instanceof StateSpec && $spec->isLying()) {
            return 'падает!';
        }

        return 'получает состояние «' . $rule->getName() . '».';
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
