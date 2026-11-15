<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service\Sheet\Spec;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Sheet\CharacterAbilityCost;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;

/**
 * Записи заклинаний в предмете-хранилище: состав и занятый Объём.
 *
 * Стоимость элемента переводится в Объём ступенчато: 1 → 10, 2 → 20,
 * 3 → 40 и далее удвоением. Записанное улучшение занимает Объём отдельно.
 */
final class CharacterItemRecorded
{
    /**
     * Объём элемента со Стоимостью 1.
     */
    public const COST_BASE = 10;

    /**
     * Во сколько раз растёт Объём с каждой ступенью Стоимости.
     */
    public const COST_FACTOR = 2;

    private readonly CharacterAbilityCost $cost;

    /**
     * Создаёт разбор записей.
     *
     * @return void
     */
    public function __construct()
    {
        $this->cost = new CharacterAbilityCost();
    }

    /**
     * Объём одного элемента по Стоимости.
     *
     * @param int $cost Стоимость.
     *
     * @return int Объём.
     */
    public function volume(int $cost): int
    {
        return self::COST_BASE * (self::COST_FACTOR ** max(0, $cost - 1));
    }

    /**
     * Занятый Объём и отказы состава.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param mixed $raw Значение payload.
     * @param array<int, string> $recordedKeywords Допустимые признаки заклинаний; пусто — без ограничения.
     *
     * @return array{volume: int, problems: array<int, array{code: string, message: string, stage: string, key: string}>}
     * Объём и отказы с относительным ключом.
     */
    public function occupied(CharacterRuleSlice $slice, mixed $raw, array $recordedKeywords = []): array
    {
        if (!is_array($raw)) {
            return ['volume' => 0, 'problems' => []];
        }

        $volume = 0;
        $problems = [];
        $seen = [];
        foreach (array_values($raw) as $position => $row) {
            $path = (string) $position . '.ruleCode';
            $code = is_array($row) ? ($row['ruleCode'] ?? null) : null;
            if (!is_string($code) || $code === '') {
                $problems[] = $this->problem('Item recorded spell is invalid', 'input', $path);
                continue;
            }

            if (isset($seen[$code])) {
                $problems[] = $this->problem('Item recorded spell is duplicated', 'input', $path);
                continue;
            }

            $seen[$code] = true;
            $spell = $this->spellCost($slice, $code);
            if ($spell === null) {
                $problems[] = $this->problem('Item recorded spell is missing', 'reference', $path);
                continue;
            }

            $rule = $slice->findLive($code);
            if ($recordedKeywords !== []
                && $rule !== null
                && array_intersect($recordedKeywords, $rule->getKeywordCodes()) === []) {
                $problems[] = $this->problem(
                    'Item recorded spell is not allowed by the storage',
                    'reference',
                    $path,
                );
            }

            $volume += $this->volume($spell);
            $volume += $this->upgradesVolume($slice, $row, $code, $position, $problems);
        }

        return ['volume' => $volume, 'problems' => $problems];
    }

    /**
     * Свободный Объём под ману.
     *
     * @param int $volume Объём.
     * @param int $occupied Занятый Объём.
     *
     * @return int Лимит маны.
     */
    public function manaLimit(int $volume, int $occupied): int
    {
        return max(0, $volume - $occupied);
    }

    /**
     * Стоимость заклинания из первой платной зоны.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return int|null Стоимость или null.
     */
    private function spellCost(CharacterRuleSlice $slice, string $code): ?int
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilitySpellSpec) {
            return null;
        }

        $cost = $this->cost->firstLevel($spec->getBase(), []);

        return $cost === null || $cost < 1 ? null : $cost;
    }

    /**
     * Объём записанных улучшений и их отказы.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка записи.
     * @param string $spellCode Код записанного заклинания.
     * @param int $position Позиция.
     * @param array<int, array{code: string, message: string, stage: string, key: string}> $problems Накопитель.
     *
     * @return int Объём.
     */
    private function upgradesVolume(
        CharacterRuleSlice $slice,
        array $row,
        string $spellCode,
        int $position,
        array &$problems,
    ): int {
        $raw = $row['upgrades'] ?? [];
        if (!is_array($raw)) {
            $problems[] = $this->problem(
                'Item recorded upgrades are invalid',
                'input',
                (string) $position . '.upgrades',
            );

            return 0;
        }

        $volume = 0;
        $seen = [];
        foreach (array_values($raw) as $index => $code) {
            $path = (string) $position . '.upgrades.' . $index;
            if (!is_string($code) || $code === '') {
                $problems[] = $this->problem('Item recorded upgrade is invalid', 'input', $path);
                continue;
            }

            if (isset($seen[$code])) {
                $problems[] = $this->problem('Item recorded upgrade is duplicated', 'input', $path);
                continue;
            }

            $seen[$code] = true;
            $cost = $this->upgradeCost($slice, $code);
            if ($cost === null) {
                $problems[] = $this->problem('Item recorded upgrade is missing', 'reference', $path);
                continue;
            }

            if (!$this->upgradeOf($slice, $code, $spellCode)) {
                $problems[] = $this->problem('Item recorded upgrade belongs to another spell', 'reference', $path);
                continue;
            }

            $volume += $this->volume($cost);
        }

        return $volume;
    }

    /**
     * Улучшение привязано к этому заклинанию или общее для пути.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код улучшения.
     * @param string $spellCode Код записанного заклинания.
     *
     * @return bool true, если привязка подходит.
     */
    private function upgradeOf(CharacterRuleSlice $slice, string $code, string $spellCode): bool
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilityWithBase) {
            return false;
        }

        $parent = $spec->getBase()->getParentAbilityCode();

        return $parent === null || $parent === '' || $parent === $spellCode;
    }

    /**
     * Стоимость улучшения.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return int|null Стоимость или null.
     */
    private function upgradeCost(CharacterRuleSlice $slice, string $code): ?int
    {
        $rule = $slice->findLive($code);
        $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
        if (!$spec instanceof AbilityWithBase || $spec->getBase()->getSpellUpgrade() === null) {
            return null;
        }

        $cost = $this->cost->firstLevel($spec->getBase(), []);

        return $cost === null || $cost < 1 ? null : $cost;
    }

    /**
     * Один отказ.
     *
     * @param string $message Текст.
     * @param string $stage Стадия.
     * @param string $key Ключ внутри payload.
     *
     * @return array{code: string, message: string, stage: string, key: string} Отказ.
     */
    private function problem(string $message, string $stage, string $key): array
    {
        return ['code' => 'CHARACTER_ITEM_RECORDED', 'message' => $message, 'stage' => $stage, 'key' => $key];
    }
}
