<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\CheckSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Шкала decline: слабость, недееспособность, бессознательность.
 *
 * Проверка только выбирает исход. Движение шкалы (снять старые строки,
 * поставить новую, не понижать при росте) живёт здесь и одинаково для
 * истощения из удара и для прироста из действия. Чтение рангов — в
 * GameStateScale.
 */
final class GameStateDecline
{
    private readonly GameStateScale $scale;

    /**
     * Создаёт шкалу.
     *
     * @return void
     */
    public function __construct()
    {
        $this->scale = new GameStateScale();
    }

    /**
     * Коды исходов проверки или все decline-состояния ревизии.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param CheckSpec $check Проверка.
     *
     * @return list<string> Коды.
     */
    public function codes(CharacterRuleSlice $slice, CheckSpec $check): array
    {
        return $this->scale->codes($slice, $check);
    }

    /**
     * Ранг состояния по флагам decline_*.
     *
     * @param StateSpec $spec Состояние.
     *
     * @return int|null Ранг 1–3 или null.
     */
    public function rank(StateSpec $spec): ?int
    {
        return $this->scale->rank($spec);
    }

    /**
     * Ранг состояния по коду или null, если это не decline.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string|null $code Код состояния.
     *
     * @return int|null Ранг или null.
     */
    public function rankOf(CharacterRuleSlice $slice, ?string $code): ?int
    {
        return $this->scale->rankOf($slice, $code);
    }

    /**
     * Ранг кода среди исходов проверки.
     *
     * @param CheckSpec $check Проверка.
     * @param string $code Код состояния.
     *
     * @return int|null Ранг или null.
     */
    public function rankInCheck(CheckSpec $check, string $code): ?int
    {
        return $this->scale->rankInCheck($check, $code);
    }

    /**
     * Текущий ранг шкалы по листу.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param CheckSpec $check Проверка.
     *
     * @return int|null Ранг или null.
     *
     * @throws GameInvalidException Если лист битый.
     */
    public function current(CharacterRuleSlice $slice, array $sheet, CheckSpec $check): ?int
    {
        return $this->scale->current($slice, $sheet, $check);
    }

    /**
     * Движение шкалы: рост только вверх, спад только вниз.
     *
     * @param bool $rose Прирост.
     * @param int|null $current Текущий ранг.
     * @param array{code: string, rank: int}|null $next Исход.
     *
     * @return array<string, mixed>|null Операция или null.
     */
    public function change(bool $rose, ?int $current, ?array $next): ?array
    {
        $nextRank = $next['rank'] ?? 0;
        $currentRank = $current ?? 0;
        $moves = $rose ? $nextRank > $currentRank : $nextRank < $currentRank;
        if (!$moves) {
            return null;
        }

        if ($next === null) {
            return ['kind' => 'replaceDecline'];
        }

        return ['kind' => 'replaceDecline', 'stateRuleCode' => $next['code']];
    }

    /**
     * Операции листа по движению шкалы.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed>|null $operation ReplaceDecline или null.
     * @param array<int, string> $declineCodes Коды исходов проверки.
     *
     * @return list<array<string, mixed>> removeState и putState.
     */
    public function operations(
        array $sheet,
        CharacterRuleSlice $slice,
        ?array $operation,
        array $declineCodes = [],
    ): array {
        if ($operation === null) {
            return [];
        }

        $ops = $this->removeOperations($sheet, $slice, $declineCodes);
        $next = $operation['stateRuleCode'] ?? null;
        if (is_string($next) && $next !== '') {
            $ops[] = ['kind' => 'putState', 'stateRuleCode' => $next];
        }

        return $ops;
    }

    /**
     * Вид исхода для сообщения.
     *
     * @param array{code: string, rank: int}|null $next Исход.
     *
     * @return string Вид.
     */
    public function kind(?array $next): string
    {
        return match ($next['rank'] ?? null) {
            1 => 'weakness',
            2 => 'disabled',
            3 => 'unconscious',
            default => 'clear',
        };
    }

    /**
     * Снимает прежние строки шкалы.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, string> $declineCodes Коды исходов проверки.
     *
     * @return list<array<string, mixed>> removeState.
     */
    private function removeOperations(array $sheet, CharacterRuleSlice $slice, array $declineCodes): array
    {
        $ops = [];
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return $ops;
        }

        foreach ($states as $row) {
            if (!is_array($row) || !is_int($row['id'] ?? null)) {
                continue;
            }

            if ($this->removable($slice, $row, $declineCodes)) {
                $ops[] = ['kind' => 'removeState', 'id' => $row['id']];
            }
        }

        return $ops;
    }

    /**
     * Строка относится к decline.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка.
     * @param array<int, string> $declineCodes Коды исходов проверки.
     *
     * @return bool true, если строку надо снять.
     */
    private function removable(CharacterRuleSlice $slice, array $row, array $declineCodes): bool
    {
        $code = $row['stateRuleCode'] ?? null;
        if ($declineCodes !== []) {
            return is_string($code) && in_array($code, $declineCodes, true);
        }

        $spec = is_string($code) ? $this->scale->optionalState($slice, $code) : null;

        return $spec !== null && $this->scale->rank($spec) !== null;
    }
}
