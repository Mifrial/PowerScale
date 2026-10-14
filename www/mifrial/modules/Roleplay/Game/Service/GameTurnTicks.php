<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\MechanicBinding;
use Mifrial\Roleplay\Mechanic\Dto\ResolveActiveOptions;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IBloodClotting;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CharacteristicDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\CheckDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DamageOverTimeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DimensionalDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\FixedDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Считает операции конца хода одного листа. Лист не пишет.
 */
final class GameTurnTicks
{
    /**
     * @param GameCheckRoll $checks Бросок.
     * @param GameStrikeConsequences $consequences Проверки и урон.
     * @param GameStrikeSplits $splits Деление.
     * @param ICharacterFormulaContexts $contexts Лист.
     * @param IMechanics $mechanics Каталог.
     * @param IMechanicEngine $engine Резолв.
     */
    public function __construct(
        private readonly GameCheckRoll $checks,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameStrikeSplits $splits,
        private readonly ICharacterFormulaContexts $contexts,
        private readonly IMechanics $mechanics,
        private readonly IMechanicEngine $engine,
    ) {
    }

    /**
     * Clotting, кровь, DOT и refill.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array<string, mixed>>} Итог.
     */
    public function build(GameRecord $game, CharacterRuleSlice $slice, array $sheet): array
    {
        $operations = [];
        $rolls = [];
        $bleed = 0;
        foreach ($this->rows($sheet) as $row) {
            if (!$this->isWound($row)) {
                continue;
            }

            $built = $this->wound($game, $slice, $sheet, $row);
            $bleed += $built['tick'];
            foreach ($built['operations'] as $operation) {
                $operations[] = $operation;
            }

            if ($built['roll'] !== null) {
                $rolls[] = ['roll' => $built['roll'], 'text' => 'Свёртывание'];
            }
        }

        $blood = $this->blood($game, $slice, $sheet, $bleed);
        foreach ($blood['operations'] as $operation) {
            $operations[] = $operation;
        }

        foreach ($blood['rolls'] as $roll) {
            $rolls[] = $roll;
        }

        foreach ($this->rows($sheet) as $row) {
            if ($this->isWound($row) || !is_string($row['stateRuleCode'] ?? null) || !is_int($row['id'] ?? null)) {
                continue;
            }

            $dot = $this->dot($game, $slice, $sheet, $row);
            foreach ($dot as $operation) {
                $operations[] = $operation;
            }
        }

        $pools = $this->turnRefillCount($slice);
        if ($pools > 1) {
            throw new GameInvalidException('Game turn resource is invalid');
        }

        if ($pools === 1) {
            $operations[] = ['kind' => 'refillTurnResource'];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * Ровно одна карточка или ноль. Две и больше — отказ хода.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return int Сколько карточек.
     */
    public function turnRefillCount(CharacterRuleSlice $slice): int
    {
        $count = 0;
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $row Строка.
     *
     * @return array{tick: int, operations: list<array<string, mixed>>, roll: array<string, mixed>|null}
     */
    private function wound(GameRecord $game, CharacterRuleSlice $slice, array $sheet, array $row): array
    {
        $strength = $this->strength($row);
        $clotting = is_int($row['clotting'] ?? null) ? $row['clotting'] : 0;
        $bandage = is_int($row['bandage'] ?? null) ? $row['bandage'] : 0;
        $roll = null;
        $marker = $this->marker($slice, $row['stateRuleCode']);
        if ($marker !== null) {
            $thrown = $this->checks->throwWithDifficulty(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $marker,
                $sheet,
                ['base' => 0, 'size' => 0],
            );
            $roll = $thrown;
            if ($thrown['success'] && $thrown['rating'] > 0) {
                $clotting = min($strength, $clotting + $thrown['rating']);
            }
        }

        $operations = [];
        if ($clotting !== (is_int($row['clotting'] ?? null) ? $row['clotting'] : 0)) {
            $operations[] = ['kind' => 'patchState', 'id' => $row['id'], 'clotting' => $clotting];
        }

        $closure = min($strength, $bandage + $clotting);
        if (is_array($row['heldBy'] ?? null)) {
            $closure = max($closure, 1);
        }

        return [
            'tick' => max(0, $strength - min($strength, $closure)),
            'operations' => $operations,
            'roll' => $roll,
        ];
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array{operations: list<array<string, mixed>>, rolls: list<array{roll: array<string, mixed>, text: string}>}
     */
    private function blood(GameRecord $game, CharacterRuleSlice $slice, array $sheet, int $tick): array
    {
        $operations = [];
        $rolls = [];
        if ($tick <= 0) {
            return ['operations' => $operations, 'rolls' => $rolls];
        }

        $code = $this->oneFlag($slice, 'blood');
        $operations[] = ['kind' => 'putState', 'stateRuleCode' => $code, 'value' => $tick];
        $rolls[] = ['roll' => ['base' => $tick, 'size' => 0], 'text' => 'Кровь'];
        $total = $this->stored($sheet, $code) + $tick;
        $floor = intdiv($total, 10);
        $exhaustion = $this->oneFlag($slice, 'exhaustion');
        $delta = max(0, $floor - $this->stored($sheet, $exhaustion));
        if ($delta > 0) {
            $operations[] = $this->exhaustionWrite($slice, $sheet, $exhaustion, $delta);
            $will = $this->consequences->willForDelta($game, $sheet, $delta);
            if ($will['operation'] !== null) {
                $operations[] = $will['operation'];
            }

            if ($will['roll'] !== null) {
                $rolls[] = ['roll' => $will['roll'], 'text' => 'Воля'];
            }
        }

        if ($total >= 40) {
            $difficulty = 2 ** intdiv($total - 40, 10);
            $maim = $this->consequences->maimRoll($game, $sheet, $difficulty);
            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $this->consequences->singleState($game, 'maim'),
                'value' => $maim['rating'],
            ];
            $rolls[] = ['roll' => $maim, 'text' => 'Увечье крови'];
        }

        return ['operations' => $operations, 'rolls' => $rolls];
    }

    /**
     * @param array<string, mixed> $row Строка.
     *
     * @return list<array<string, mixed>>
     */
    private function dot(GameRecord $game, CharacterRuleSlice $slice, array $sheet, array $row): array
    {
        $rule = $slice->findLive($row['stateRuleCode']);
        $spec = $rule instanceof CharacterResolvedRule && !$rule->isSpecBroken() ? $rule->getSpec() : null;
        if (!$spec instanceof StateSpec) {
            return [];
        }

        $effect = null;
        foreach ($spec->getEffects() as $candidate) {
            if ($candidate instanceof DamageOverTimeEffect) {
                $effect = $candidate;
            }
        }

        if ($effect === null) {
            return [];
        }

        $step = $effect->getPeriodStep();
        if ($step !== null && $step !== '' && $step !== 'turn') {
            return [];
        }

        if ($effect->getDamageTypeCode() === null || $effect->getDamageTypeCode() === '') {
            return [];
        }

        $period = $effect->getPeriodValue();
        $left = is_int($row['dotTurnsLeft'] ?? null) ? $row['dotTurnsLeft'] : $period;
        if ($period !== null && $period > 1) {
            $left = ($left ?? $period) - 1;
            if ($left > 0) {
                return [['kind' => 'patchState', 'id' => $row['id'], 'dotTurnsLeft' => $left]];
            }

            $left = $period;
        }

        $injury = $this->injuryOf($effect, $row);
        $split = $this->splits->operation($slice, $this->contexts, $sheet, $injury);
        $effects = $this->consequences->effects($game, $sheet, $injury, 0, $split, $effect->getDamageTypeCode(), false);
        $operations = $effects['operations'];
        $next = $this->decay($effect, $row);
        if ($next === null || $this->strength($next) <= 0) {
            $operations[] = ['kind' => 'removeState', 'id' => $row['id']];
        } else {
            $patch = ['kind' => 'patchState', 'id' => $row['id'], 'value' => $next['value']];
            if ($period !== null && $period > 1) {
                $patch['dotTurnsLeft'] = $left;
            }

            $operations[] = $patch;
        }

        return $operations;
    }

    /**
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed>|null Строка после decay или null, если силу не трогаем и она уже есть.
     */
    private function decay(DamageOverTimeEffect $effect, array $row): ?array
    {
        $decay = $effect->getDecay();
        if ($decay === null || $decay instanceof CharacteristicDecay || $decay instanceof CheckDecay) {
            return $row;
        }

        $strength = $this->strength($row);
        if ($decay instanceof FixedDecay) {
            $row['value'] = $strength - $decay->getValue();

            return $row;
        }

        if ($decay instanceof DimensionalDecay && is_array($row['value'] ?? null)) {
            $row['value'] = [
                'base' => $row['value']['base'] - $decay->getValue()->getBase(),
                'size' => $row['value']['size'] - $decay->getValue()->getSize(),
            ];

            return $row;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row Строка.
     */
    private function injuryOf(DamageOverTimeEffect $effect, array $row): DimensionalNumber
    {
        if ($effect->getDamageKind() === 'value') {
            $value = $row['value'] ?? 0;
            if (is_array($value)) {
                return new DimensionalNumber($value['base'], $value['size']);
            }

            return new DimensionalNumber(is_int($value) ? $value : 0, 0);
        }

        return new DimensionalNumber($effect->getAmount(), 0);
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     *
     * @return array<string, mixed>
     */
    private function exhaustionWrite(CharacterRuleSlice $slice, array $sheet, string $code, int $delta): array
    {
        $rule = $slice->findLive($code);
        $spec = $rule instanceof CharacterResolvedRule ? $rule->getSpec() : null;
        $exists = $this->stored($sheet, $code) > 0 || $this->hasRow($sheet, $code);
        if ($spec instanceof StateSpec && $spec->getAggregation() === 'independent' && $exists) {
            return [
                'kind' => 'putDamageSplit',
                'remainder' => ['base' => $this->remainder($slice, $sheet), 'size' => 0],
                'quotient' => $delta,
            ];
        }

        return ['kind' => 'putState', 'stateRuleCode' => $code, 'value' => $delta];
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function remainder(CharacterRuleSlice $slice, array $sheet): int
    {
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof StateSpec && $spec->isDamageRemainder()) {
                return $this->stored($sheet, $rule->getCode());
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function hasRow(array $sheet, string $code): bool
    {
        foreach ($this->rows($sheet) as $row) {
            if (($row['stateRuleCode'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function stored(array $sheet, string $code): int
    {
        $total = 0;
        foreach ($this->rows($sheet) as $row) {
            if (($row['stateRuleCode'] ?? null) === $code) {
                $total += $this->strength($row);
            }
        }

        return $total;
    }

    private function oneFlag(CharacterRuleSlice $slice, string $flag): string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec) {
                continue;
            }

            $hit = ($flag === 'blood' && $spec->isBloodLoss()) || ($flag === 'exhaustion' && $spec->isDamageExhaustion());
            if ($hit) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) !== 1) {
            throw new GameInvalidException('Game turn state is invalid');
        }

        return $matched[0];
    }

    /**
     * @param array<string, mixed> $row Строка.
     */
    private function marker(CharacterRuleSlice $slice, mixed $code): ?string
    {
        if (!is_string($code)) {
            return null;
        }

        $rule = $slice->findLive($code);
        if (!$rule instanceof CharacterResolvedRule) {
            return null;
        }

        foreach ($rule->getMechanics() as $binding) {
            if (!is_array($binding) || !is_int($binding['mechanic_id'] ?? null)) {
                continue;
            }

            $handler = $this->handler($code, $binding['mechanic_id']);
            if (!$handler instanceof IBloodClotting) {
                continue;
            }

            $payload = $binding['mechanic_payload'] ?? [];
            $check = is_array($payload) ? ($payload['check_code'] ?? null) : null;
            if (!is_string($check) || $check === '') {
                throw new GameInvalidException('Game turn clotting check is invalid');
            }

            return $check;
        }

        return null;
    }

    private function handler(string $ruleCode, int $mechanicId): IMechanicHandler
    {
        try {
            $resolved = $this->engine->resolveActive(
                [new MechanicBinding($ruleCode, $mechanicId, null)],
                $this->mechanics->getList(),
                new ResolveActiveOptions(),
            );
        } catch (MechanicInvalidException $exception) {
            throw new GameInvalidException('Game turn clotting check is invalid', $exception);
        }

        return $resolved[0]->getHandler();
    }

    /**
     * @param array<string, mixed> $row Строка.
     */
    private function isWound(array $row): bool
    {
        return is_int($row['id'] ?? null) && (array_key_exists('bandage', $row) || array_key_exists('clotting', $row));
    }

    /**
     * @param array<string, mixed> $row Строка.
     */
    private function strength(array $row): int
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
     * @param array<string, mixed> $sheet Лист.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $sheet): array
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
}
