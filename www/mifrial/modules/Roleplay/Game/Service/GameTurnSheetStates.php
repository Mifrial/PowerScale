<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Чтение строк состояний листа для тиков конца хода.
 */
final class GameTurnSheetStates
{
    /**
     * Строки состояний листа.
     *
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>> Строки.
     */
    public function rows(array $sheet): array
    {
        $states = $sheet['states'] ?? [];
        if (!is_array($states)) {
            return [];
        }

        $rows = [];
        foreach ($states as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Строка является раной.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return bool true, если есть повязка или свёртывание.
     */
    public function isWound(array $row): bool
    {
        return is_int($row['id'] ?? null) && (array_key_exists('bandage', $row) || array_key_exists('clotting', $row));
    }

    /**
     * Сила состояния как целое.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return int Сила.
     */
    public function strength(array $row): int
    {
        $value = $row['value'] ?? 0;
        if (is_int($value)) {
            return $value;
        }

        if (is_array($value) && is_int($value['base'] ?? null)) {
            return $value['base'];
        }

        return 0;
    }

    /**
     * Суммарная сила состояния в листе.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код состояния.
     *
     * @return int Сила.
     */
    public function stored(array $sheet, string $code): int
    {
        $total = 0;
        foreach ($this->rows($sheet) as $row) {
            if (($row['stateRuleCode'] ?? null) === $code) {
                $total += $this->strength($row);
            }
        }

        return $total;
    }

    /**
     * В листе есть строка состояния.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код состояния.
     *
     * @return bool true, если строка есть.
     */
    public function hasRow(array $sheet, string $code): bool
    {
        foreach ($this->rows($sheet) as $row) {
            if (($row['stateRuleCode'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Остаток урона листа.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return int Остаток.
     */
    public function remainder(CharacterRuleSlice $slice, array $sheet): int
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $spec->isDamageRemainder()) {
                return $this->stored($sheet, $rule->getCode());
            }
        }

        return 0;
    }
}
