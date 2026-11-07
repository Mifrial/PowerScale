<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Снятие и уменьшение хвостов ожидания действия.
 */
final class GamePendingActionReduce
{
    /**
     * Уменьшает старые последействия на фактическую трату.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     * @param array<string, int> $spentAmounts Трата по ресурсам.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    public function reduceAfterAction(array $waiting, array $spentAmounts): array
    {
        $kept = [];
        foreach ($waiting as $entry) {
            $keptEntry = $this->reduceAfterActionEntry($entry, $spentAmounts);
            if ($keptEntry !== null) {
                $kept[] = $keptEntry;
            }
        }

        return $kept;
    }



    /**
     * Снимает хвосты прошлого действия: их поглощает текущее действие.
     *
     * Снимок прошлого удара тоже живёт до следующего действия; закрытие удара
     * положит новый снимок после этой очистки.
     *
     * @param array<int, mixed> $waiting Ожидание актора.
     *
     * @return array<int, mixed> Оставшиеся записи.
     */
    public function withoutAttackTails(array $waiting): array
    {
        return array_values(array_filter(
            $waiting,
            static function (mixed $entry): bool {
                $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
                $type = is_array($effect) ? ($effect['type'] ?? null) : null;

                return $type !== 'next_action_attack_accuracy'
                    && $type !== 'next_action_attack_score_adjust'
                    && $type !== 'next_action_attack_dodge_soak_from_reaction'
                    && $type !== 'last_strike_snapshot';
            },
        ));
    }

    /**
     * Подходит ли хвост под текущий бросок.
     *
     * @param array<string, mixed> $effect Эффект хвоста.
     * @param string $profileType Тип профиля.
     * @param array<int, string> $targetKeys Цели атаки.
     * @param int $hitNumber Номер попадания.
     *
     * @return bool true, если подходит.
     */
    public function tailApplies(array $effect, string $profileType, array $targetKeys, int $hitNumber): bool
    {
        $type = $effect['type'] ?? null;
        if ($type !== 'next_action_attack_accuracy' && $type !== 'next_action_attack_score_adjust') {
            return false;
        }

        if (!$this->sameTarget($effect, $targetKeys)) {
            return false;
        }

        return $type !== 'next_action_attack_accuracy'
            || GamePendingActionScope::applies($effect['scope'] ?? null, $profileType, $hitNumber);
    }



    /**
     * Оставляет хвост смягчения только для атаки, уложившейся в потолок ОД.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<int, mixed> $entries Записи после cost-эффектов.
     * @param bool $forAttack Команда — атака.
     * @param array<string, int> $amounts Итоговая цена по ресурсам.
     *
     * @return array<int, mixed> Записи.
     */
    public function keepSoakTail(
        CharacterRuleSlice $slice,
        array $entries,
        bool $forAttack,
        array $amounts,
    ): array {
        $cost = $this->actionCost($slice, $amounts);
        $kept = [];
        foreach ($entries as $entry) {
            if (!$this->isSoakTail($entry)) {
                $kept[] = $entry;
                continue;
            }

            $effect = is_array($entry['effect'] ?? null) ? $entry['effect'] : [];
            if ($forAttack && $this->soakTailFits($effect, $cost)) {
                $entry['effect']['armed'] = true;
                $kept[] = $entry;
            }
        }

        return $kept;
    }

    /**
     * Уменьшает остаток одной записи последействия.
     *
     * @param mixed $entry Запись ожидания.
     * @param array<string, int> $spentAmounts Трата по ресурсам.
     *
     * @return mixed Запись или null, если погашена.
     */
    private function reduceAfterActionEntry(mixed $entry, array $spentAmounts): mixed
    {
        $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
        $keep = true;
        if (is_array($effect) && ($effect['type'] ?? null) === 'after_action_until_resource_spent_check_modifier') {
            $resource = $effect['resource_code'] ?? null;
            $amount = $effect['amount'] ?? null;
            $spent = $spentAmounts[$resource] ?? 0;
            if ($spent > 0) {
                $remaining = $amount - $spent;
                $keep = $remaining > 0;
                if ($keep) {
                    $effect['amount'] = $remaining;
                    $entry['effect'] = $effect;
                }
            }
        }

        return $keep ? $entry : null;
    }

    /**
     * Цель хвоста совпала с целями текущей атаки.
     *
     * @param array<string, mixed> $effect Эффект хвоста.
     * @param array<int, string> $targetKeys Цели атаки.
     *
     * @return bool true, если цель подходит или не требуется.
     */
    private function sameTarget(array $effect, array $targetKeys): bool
    {
        if (($effect['same_target'] ?? false) !== true) {
            return true;
        }

        $targetKey = $effect['targetKey'] ?? null;

        return is_string($targetKey) && in_array($targetKey, $targetKeys, true);
    }

    /**
     * Запись карты — хвост смягчения от Реакции.
     *
     * @param mixed $entry Запись.
     *
     * @return bool true, если хвост.
     */
    private function isSoakTail(mixed $entry): bool
    {
        $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;

        return is_array($effect) && ($effect['type'] ?? null) === 'next_action_attack_dodge_soak_from_reaction';
    }

    /**
     * Цена атаки не превышает потолок хвоста.
     *
     * @param array<string, mixed> $effect Эффект.
     * @param int $cost Цена в ОД.
     *
     * @return bool true, если хвост применяется.
     */
    private function soakTailFits(array $effect, int $cost): bool
    {
        $max = $effect['max_total_action_cost'] ?? null;

        return !is_int($max) || $cost <= $max;
    }

    /**
     * Фактическая цена действия в ОД после cost-эффектов.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, int> $amounts Цена по ресурсам.
     *
     * @return int Очки действия.
     */
    private function actionCost(CharacterRuleSlice $slice, array $amounts): int
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                $value = $amounts[$rule->getCode()] ?? null;

                return is_int($value) ? $value : 0;
            }
        }

        $fallback = $amounts['action-points'] ?? null;

        return is_int($fallback) ? $fallback : 0;
    }
}
