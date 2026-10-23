<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityPlainSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityProcessSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AttackSrFromPreviousEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\RequirePreviousAttackEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\RequirePreviousStrikeEffect;

/**
 * Снимок прошлого удара: запись, чтение и гейты добивания.
 */
final class GameLastStrikeSnapshot
{
    /**
     * Заменяет снимок прошлого удара в карте ожидания.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $sourceRuleCode Карточка удара.
     * @param string $kind Вид lethal или other.
     * @param array<int, array<string, mixed>> $hits Удары.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function store(
        array $pending,
        string $actorKind,
        int $actorId,
        string $sourceRuleCode,
        string $kind,
        array $hits,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (is_array($effect) && ($effect['type'] ?? null) === 'last_strike_snapshot') {
                continue;
            }

            $kept[] = $entry;
        }

        $kept[] = [
            'sourceRuleCode' => $sourceRuleCode,
            'effect' => [
                'type' => 'last_strike_snapshot',
                'kind' => $kind,
                'hits' => $hits,
            ],
        ];
        $pending[$key] = $kept;

        return $pending;
    }

    /**
     * Снимок прошлого удара актора.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     *
     * @return array<string, mixed>|null Снимок или null.
     */
    public function snapshotOf(array $pending, string $actorKind, int $actorId): ?array
    {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        foreach (array_reverse($waiting) as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'last_strike_snapshot') {
                continue;
            }

            return $effect;
        }

        return null;
    }

    /**
     * Успех прошлого удара по цели.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $targetKey Ключ цели.
     *
     * @return int Успех или 0.
     */
    public function previousSr(array $pending, string $actorKind, int $actorId, string $targetKey): int
    {
        $snapshot = $this->snapshotOf($pending, $actorKind, $actorId);
        if ($snapshot === null) {
            return 0;
        }

        foreach ($this->hitsOf($snapshot) as $hit) {
            if (($hit['targetKey'] ?? null) === $targetKey) {
                return is_int($hit['attackSr'] ?? null) ? $hit['attackSr'] : 0;
            }
        }

        return 0;
    }

    /**
     * Вид удара карточки: lethal, если она добивает от прошлого успеха.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return string lethal или other.
     */
    public function kindOf(CharacterRuleSlice $slice, string $actionRuleCode): string
    {
        $base = $this->baseOf($slice, $actionRuleCode);
        if ($base === null) {
            return 'other';
        }

        foreach ($base->getActionEffects() as $effect) {
            if ($effect instanceof AttackSrFromPreviousEffect) {
                return 'lethal';
            }
        }

        return 'other';
    }

    /**
     * Успех удара с надбавкой от прошлого успеха.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param int $success Текущий успех.
     * @param int $previousSr Успех прошлого удара.
     *
     * @return int Итоговый успех.
     */
    public function boostSuccess(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        int $success,
        int $previousSr,
    ): int {
        if ($success <= 0 || $previousSr <= 0) {
            return $success;
        }

        $base = $this->baseOf($slice, $actionRuleCode);
        if ($base === null) {
            return $success;
        }

        foreach ($base->getActionEffects() as $effect) {
            if (!$effect instanceof AttackSrFromPreviousEffect) {
                continue;
            }

            $divisor = max(1, $effect->getFloorDiv());
            $bonus = intdiv($previousSr, $divisor);
            $capped = $effect->getCap() === 'double_this' ? $success * 2 : $success + $bonus;

            return min($success + $bonus, $capped);
        }

        return $success;
    }

    /**
     * Проверяет гейт добивания карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<int, string> $targetKeys Цели объявляемой атаки.
     *
     * @return void
     *
     * @throws GameInvalidException Если прошлого удара нет или цель чужая.
     */
    public function assertFollowUp(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $pending,
        string $actorKind,
        int $actorId,
        array $targetKeys,
    ): void {
        $base = $this->baseOf($slice, $actionRuleCode);
        if ($base === null) {
            return;
        }

        foreach ($base->getActionEffects() as $effect) {
            if ($effect instanceof RequirePreviousAttackEffect) {
                $this->assertPreviousAttack(
                    $effect,
                    $this->snapshotOf($pending, $actorKind, $actorId),
                    $targetKeys,
                );

                return;
            }

            if ($effect instanceof RequirePreviousStrikeEffect) {
                $this->assertPreviousStrike(
                    $effect,
                    $this->snapshotOf($pending, $actorKind, $actorId),
                    $targetKeys,
                );

                return;
            }
        }
    }

    /**
     * Гейт require_previous_attack.
     *
     * @param RequirePreviousAttackEffect $effect Эффект.
     * @param array<string, mixed>|null $snapshot Снимок.
     * @param array<int, string> $targetKeys Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если условие не выполнено.
     */
    private function assertPreviousAttack(
        RequirePreviousAttackEffect $effect,
        ?array $snapshot,
        array $targetKeys,
    ): void {
        $hits = $snapshot === null ? [] : $this->hitsOf($snapshot);
        if ($hits === []) {
            throw new GameInvalidException('Game strike follow-up requires a previous attack');
        }

        if ($effect->isAllDamaged()) {
            foreach ($hits as $hit) {
                if (($hit['damaged'] ?? false) !== true) {
                    throw new GameInvalidException('Game strike follow-up requires a damaging previous attack');
                }
            }
        }

        if ($effect->isSingleStrike() && count($targetKeys) !== 1) {
            throw new GameInvalidException('Game strike follow-up requires a single target');
        }

        $this->assertTargetsOfHits($hits, $targetKeys);
    }

    /**
     * Гейт require_previous_strike.
     *
     * @param RequirePreviousStrikeEffect $effect Эффект.
     * @param array<string, mixed>|null $snapshot Снимок.
     * @param array<int, string> $targetKeys Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если условие не выполнено.
     */
    private function assertPreviousStrike(
        RequirePreviousStrikeEffect $effect,
        ?array $snapshot,
        array $targetKeys,
    ): void {
        if ($snapshot === null || ($snapshot['kind'] ?? null) === $effect->getNotKind()) {
            throw new GameInvalidException('Game strike follow-up requires another previous strike');
        }

        $hits = [];
        foreach ($this->hitsOf($snapshot) as $hit) {
            $attackSr = $hit['attackSr'] ?? null;
            if (is_int($attackSr) && $attackSr >= $effect->getMinSr()) {
                $hits[] = $hit;
            }
        }

        if ($hits === []) {
            throw new GameInvalidException('Game strike follow-up requires a successful previous strike');
        }

        $this->assertTargetsOfHits($hits, $targetKeys);
    }

    /**
     * Все цели должны быть среди ударов снимка.
     *
     * @param array<int, array<string, mixed>> $hits Удары.
     * @param array<int, string> $targetKeys Цели.
     *
     * @return void
     *
     * @throws GameInvalidException Если цель чужая.
     */
    private function assertTargetsOfHits(array $hits, array $targetKeys): void
    {
        $allowed = [];
        foreach ($hits as $hit) {
            if (is_string($hit['targetKey'] ?? null)) {
                $allowed[] = $hit['targetKey'];
            }
        }

        foreach ($targetKeys as $targetKey) {
            if (!in_array($targetKey, $allowed, true)) {
                throw new GameInvalidException('Game strike follow-up target is invalid');
            }
        }
    }

    /**
     * Удары снимка списком.
     *
     * @param array<string, mixed> $snapshot Снимок.
     *
     * @return array<int, array<string, mixed>> Удары.
     */
    private function hitsOf(array $snapshot): array
    {
        $hits = $snapshot['hits'] ?? null;
        if (!is_array($hits)) {
            return [];
        }

        return array_values(array_filter($hits, static fn (mixed $hit): bool => is_array($hit)));
    }

    /**
     * База поддерживаемой карточки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     *
     * @return AbilityBase|null База или null.
     */
    private function baseOf(CharacterRuleSlice $slice, string $actionRuleCode): ?AbilityBase
    {
        if ($actionRuleCode === '') {
            return null;
        }

        $spec = $slice->findLive($actionRuleCode)?->getSpec();
        if (
            $spec instanceof AbilityActionSpec
            || $spec instanceof AbilityPlainSpec
            || $spec instanceof AbilityProcessSpec
            || $spec instanceof AbilitySpellSpec
        ) {
            return $spec->getBase();
        }

        return null;
    }
}
