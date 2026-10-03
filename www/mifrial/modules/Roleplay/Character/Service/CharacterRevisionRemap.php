<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;

/**
 * Перенос choices на целевой срез по code. Записи не делает.
 */
final class CharacterRevisionRemap
{
    /**
     * Собирает choices целевой ревизии из сохранённых.
     *
     * @param array<string, mixed> $stored Choices actual.
     * @param CharacterRuleSlice $source Срез текущей ревизии.
     * @param CharacterRuleSlice $target Срез цели.
     *
     * @return array{changed: bool, choices: array<string, mixed>} Документ и признак правки.
     */
    public function remap(array $stored, CharacterRuleSlice $source, CharacterRuleSlice $target): array
    {
        $changed = false;
        $raceCode = $this->raceCode($stored, $target, $changed);
        $abilities = $this->abilities($this->rows($stored, 'abilities'), $target, $changed);
        $inventory = $this->inventory($this->rows($stored, 'inventory'), $source, $target, $changed);
        $choices = $stored;
        $choices['raceCode'] = $raceCode;
        $choices['abilities'] = $abilities;
        $choices['inventory'] = $inventory;
        $choices['limits'] = $this->limits($stored);

        return ['changed' => $changed, 'choices' => $choices];
    }

    /**
     * Пустая раса, если кода нет среди живых.
     *
     * @param array<string, mixed> $stored Choices.
     * @param CharacterRuleSlice $target Срез цели.
     * @param bool $changed Признак правки.
     *
     * @return string Код или пустая строка.
     */
    private function raceCode(array $stored, CharacterRuleSlice $target, bool &$changed): string
    {
        $raceCode = $stored['raceCode'] ?? '';
        if (!is_string($raceCode)) {
            $changed = true;

            return '';
        }

        if ($raceCode !== '' && $target->findLive($raceCode) === null) {
            $changed = true;

            return '';
        }

        return $raceCode;
    }

    /**
     * Способности с живым code. Остальные строки снимаются.
     *
     * @param array<mixed> $abilities Список.
     * @param CharacterRuleSlice $target Срез цели.
     * @param bool $changed Признак правки.
     *
     * @return array<int, mixed> Список.
     */
    private function abilities(array $abilities, CharacterRuleSlice $target, bool &$changed): array
    {
        $rows = [];
        foreach ($abilities as $ability) {
            if (!is_array($ability)) {
                $changed = true;
                continue;
            }

            $ruleCode = $ability['ruleCode'] ?? null;
            if (is_string($ruleCode) && $target->findLive($ruleCode) === null) {
                $changed = true;
                continue;
            }

            $rows[] = $ability;
        }

        return $rows;
    }

    /**
     * Живой предмет остаётся. Без живого code — custom с именем исходного правила.
     *
     * @param array<mixed> $inventory Список.
     * @param CharacterRuleSlice $source Срез источника.
     * @param CharacterRuleSlice $target Срез цели.
     * @param bool $changed Признак правки.
     *
     * @return array<int, mixed> Список.
     */
    private function inventory(
        array $inventory,
        CharacterRuleSlice $source,
        CharacterRuleSlice $target,
        bool &$changed,
    ): array {
        $rows = [];
        foreach ($inventory as $item) {
            if (!is_array($item)) {
                $changed = true;
                continue;
            }

            $rows[] = $this->item($item, $source, $target, $changed);
        }

        return $rows;
    }

    /**
     * Один предмет.
     *
     * @param array<mixed> $item Строка.
     * @param CharacterRuleSlice $source Срез источника.
     * @param CharacterRuleSlice $target Срез цели.
     * @param bool $changed Признак правки.
     *
     * @return array<mixed> Строка.
     */
    private function item(array $item, CharacterRuleSlice $source, CharacterRuleSlice $target, bool &$changed): array
    {
        $ruleCode = $item['ruleCode'] ?? null;
        if (!is_string($ruleCode)) {
            return $item;
        }

        if ($target->findLive($ruleCode) !== null) {
            return $item;
        }

        $changed = true;
        $rule = $source->findLive($ruleCode);
        unset($item['ruleCode']);
        $item['custom'] = [
            'name' => $rule === null ? $ruleCode : $rule->getName(),
            'description' => $rule === null ? '' : $rule->getDescription(),
        ];

        return $item;
    }

    /**
     * Потолки листа без бюджета шопа.
     *
     * @param array<string, mixed> $stored Choices.
     *
     * @return array<mixed> os и or.
     */
    private function limits(array $stored): array
    {
        $limits = $stored['limits'] ?? [];
        if (!is_array($limits)) {
            return [];
        }

        unset($limits['money']);

        return $limits;
    }

    /**
     * Список choices или пустой.
     *
     * @param array<string, mixed> $stored Choices.
     * @param string $key Ключ.
     *
     * @return array<mixed> Строки.
     */
    private function rows(array $stored, string $key): array
    {
        $rows = $stored[$key] ?? [];

        return is_array($rows) ? $rows : [];
    }
}
