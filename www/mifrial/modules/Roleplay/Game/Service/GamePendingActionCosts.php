<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Mechanic\Dto\RollAdjustment;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\AfterActionUntilResourceSpentCheckModifierEffect;

/**
 * Читает, снимает и ставит карту ожидания следующего действия.
 */
final class GamePendingActionCosts
{
    private readonly GamePendingActionTails $tails;

    private readonly GamePendingActionReduce $reduce;

    private readonly GamePendingActionSkills $skills;

    /**
     * Создаёт карту ожидания.
     *
     * @return void
     */
    public function __construct()
    {
        $this->tails = new GamePendingActionTails();
        $this->reduce = new GamePendingActionReduce();
        $this->skills = new GamePendingActionSkills($this->tails);
    }

    /**
     * Сдвигает целые траты удара и возвращает новую карту. Пол 2 не ставит.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы атакующего.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид.
     * @param int $actorId Id.
     * @param array<string, mixed> $pending Карта.
     * @param array<int, ResourceSpend> $spends Траты карточки.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>} Итог.
     */
    public function adjustSpends(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $spends,
    ): array {
        $split = $this->splitSpends($spends);
        $declared = $this->declare($slice, $choices, $actionRuleCode, $actorKind, $actorId, $pending, $split['amounts'], true);
        $kept = $split['kept'];
        foreach ($declared['amounts'] as $code => $amount) {
            $kept[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return ['spends' => $kept, 'pending' => $declared['pending']];
    }

    /**
     * Начало действия: применяет cost-эффекты, не трогая висящие проверки.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка команды.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<string, mixed> $pending Карта боя.
     * @param array<string, int> $amounts Цены по ресурсам.
     * @param bool $forAttack Команда — атака: применить next_action_attack_cost.
     *
     * @return array{amounts: array<string, int>, pending: array<string, mixed>, touched: array<string, bool>} Итог.
     */
    public function declare(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $amounts,
        bool $forAttack = false,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $consumed = $this->consume($slice, $actionRuleCode, $waiting, $amounts, $forAttack);
        $kept = $this->reduce->keepSoakTail($slice, $consumed['kept'], $forAttack, $consumed['amounts']);

        return [
            'amounts' => $consumed['amounts'],
            'pending' => $this->storePending($pending, $key, $kept),
            'touched' => $consumed['touched'],
        ];
    }

    /**
     * Конец действия: уменьшает старые висящие проверки и дописывает новые.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка команды.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<string, mixed> $pending Карта боя.
     * @param array<string, int> $spentAmounts Фактическая трата по ресурсам.
     * @param array<int, string> $targetKeys Цели завершённой атаки.
     * @param bool $skipAfterAction Не дописывать помеху после действия.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function complete(
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        array $spentAmounts,
        array $targetKeys = [],
        bool $skipAfterAction = false,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $kept = $this->reduce->withoutAttackTails($this->reduce->reduceAfterAction($waiting, $spentAmounts));

        array_push($kept, ...$this->tails->attackTailOf($slice, $actionRuleCode, $targetKeys));
        if (!$skipAfterAction) {
            array_push($kept, ...$this->tails->afterActionOf($slice, $actionRuleCode));
        }

        array_push($kept, ...$this->skills->queued($slice, $choices, $actionRuleCode));
        array_push($kept, ...$this->tails->attackCostOf($slice, $actionRuleCode));
        array_push($kept, ...$this->tails->dodgeSoakTailOf($slice, $actionRuleCode));

        return $this->storePending($pending, $key, $kept);
    }

    /**
     * Добавляет готовые эффекты в ожидание актора.
     *
     * Срез 1 применяет только хвост проверки после действия; остальные
     * эффекты завершения процесса подключит рантайм счётчиков.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param array<int, \Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\ActionEffect> $effects Эффекты.
     * @param string $sourceCode Источник.
     *
     * @return array<string, mixed> Новая карта.
     */
    public function appendEffects(
        array $pending,
        string $actorKind,
        int $actorId,
        array $effects,
        string $sourceCode,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        foreach ($effects as $effect) {
            if (!$effect instanceof AfterActionUntilResourceSpentCheckModifierEffect) {
                continue;
            }

            $waiting[] = [
                'sourceRuleCode' => $sourceCode,
                'effect' => $this->tails->afterActionStored($effect),
            ];
        }

        return $this->storePending($pending, $key, $waiting);
    }

    /**
     * Висящие проверки актора для одного броска.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $checkCode Код проверки.
     * @param string $side Сторона attacker или defender.
     *
     * @return array<int, RollAdvantage> Записи.
     */
    public function checkAdvantages(
        array $pending,
        string $actorKind,
        int $actorId,
        string $checkCode,
        string $side,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $rows = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'after_action_until_resource_spent_check_modifier') {
                continue;
            }

            $advantage = $this->tails->checkAdvantageOf($effect, $checkCode, $side);
            if ($advantage !== null) {
                $rows[] = $advantage;
            }
        }

        return $rows;
    }

    /**
     * Хвосты прошлого действия для броска атаки.
     *
     * Берёт только записи, подходящие по компоненту, номеру попадания и цели;
     * сами записи снимает {@see self::complete()} при закрытии этого действия.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Id актора.
     * @param string $profileType Тип профиля strike, throw или shoot.
     * @param array<int, string> $targetKeys Цели текущей атаки.
     * @param int $hitNumber Номер попадания с единицы.
     *
     * @return array{accuracy: int, adjustment: RollAdjustment} Итог.
     */
    public function attackAdjustment(
        array $pending,
        string $actorKind,
        int $actorId,
        string $profileType,
        array $targetKeys = [],
        int $hitNumber = 1,
    ): array {
        $key = $actorKind . ':' . $actorId;
        $waiting = is_array($pending[$key] ?? null) ? $pending[$key] : [];
        $total = ['accuracy' => 0, 'one' => 0, 'face' => 0];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!is_array($effect) || !$this->reduce->tailApplies($effect, $profileType, $targetKeys, $hitNumber)) {
                continue;
            }

            $this->accumulate($effect, $total);
        }

        return [
            'accuracy' => $total['accuracy'],
            'adjustment' => new RollAdjustment([], $total['one'], $total['face']),
        ];
    }

    /**
     * Фильтр эффекта совпал с карточкой.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, mixed> $effect Эффект.
     *
     * @return bool true, если совпал.
     */
    public function matches(CharacterRuleSlice $slice, string $actionRuleCode, array $effect): bool
    {
        return $this->skills->matches($slice, $actionRuleCode, $effect);
    }

    /**
     * Применяет cost-эффект к ценам.
     *
     * @param array<string, int> $total Накопители броска.
     * @param array<string, mixed> $effect Эффект.
     *
     * @return void
     */
    private function accumulate(array $effect, array &$total): void
    {
        if (($effect['type'] ?? null) === 'next_action_attack_accuracy') {
            $delta = $effect['delta'] ?? null;
            if (is_int($delta)) {
                $total['accuracy'] += $delta;
            }

            return;
        }

        $one = $effect['oneDelta'] ?? null;
        $face = $effect['faceDelta'] ?? null;
        if (is_int($one)) {
            $total['one'] += $one;
        }

        if (is_int($face)) {
            $total['face'] += $face;
        }
    }

    /**
     * Расходует cost-эффекты ожидания.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<int, mixed> $waiting Ожидание актора.
     * @param array<string, int> $amounts Цены.
     * @param bool $forAttack Команда — атака.
     *
     * @return array{amounts: array<string, int>, kept: array<int, mixed>, touched: array<string, bool>} Итог.
     */
    private function consume(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $waiting,
        array $amounts,
        bool $forAttack,
    ): array {
        $kept = [];
        $touched = [];
        foreach ($waiting as $entry) {
            $effect = is_array($entry) ? ($entry['effect'] ?? null) : null;
            if (!$this->consumes($slice, $actionRuleCode, $effect, $forAttack)) {
                $kept[] = $entry;
                continue;
            }

            $this->applyCost($effect, $amounts, $touched, $forAttack);
        }

        return ['amounts' => $amounts, 'kept' => $kept, 'touched' => $touched];
    }

    /**
     * Запись расходуется этим действием.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param mixed $effect Эффект записи.
     * @param bool $forAttack Команда — атака.
     *
     * @return bool Истина, если запись снимается.
     */
    private function consumes(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        mixed $effect,
        bool $forAttack,
    ): bool {
        if (!is_array($effect)) {
            return false;
        }

        $type = $effect['type'] ?? null;
        if ($type === 'next_action_attack_cost') {
            return true;
        }

        return $type === 'next_action_cost' && $this->skills->matches($slice, $actionRuleCode, $effect);
    }

    /**
     * Применяет цену эффекта.
     *
     * @param mixed $effect Эффект записи.
     * @param array<string, int> $amounts Цены.
     * @param array<string, bool> $touched Изменённые ресурсы.
     * @param bool $forAttack Команда — атака.
     *
     * @return void
     */
    private function applyCost(mixed $effect, array &$amounts, array &$touched, bool $forAttack): void
    {
        if (!is_array($effect)) {
            return;
        }

        if (($effect['type'] ?? null) === 'next_action_attack_cost' && !$forAttack) {
            return;
        }

        $resource = $effect['resource_code'] ?? null;
        $delta = $effect['delta'] ?? null;
        if (!is_string($resource) || !is_int($delta) || !array_key_exists($resource, $amounts)) {
            return;
        }

        $amounts[$resource] += $delta;
        $touched[$resource] = true;
    }

    /**
     * Пишет ожидание актора или снимает пустое.
     *
     * @param array<string, mixed> $pending Карта боя.
     * @param string $key Ключ актора.
     * @param array<int, mixed> $kept Записи.
     *
     * @return array<string, mixed> Карта.
     */
    private function storePending(array $pending, string $key, array $kept): array
    {
        if ($kept === []) {
            unset($pending[$key]);

            return $pending;
        }

        $pending[$key] = $kept;

        return $pending;
    }

    /**
     * Делит траты на целые суммы и неразобранные записи.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array{amounts: array<string, int>, kept: array<int, ResourceSpend>} Итог.
     */
    private function splitSpends(array $spends): array
    {
        $amounts = [];
        $kept = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                $kept[] = $spend;
                continue;
            }

            $code = $spend->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $native;
        }

        return ['amounts' => $amounts, 'kept' => $kept];
    }
}
