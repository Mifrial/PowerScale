<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DamageOverTimeEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\State\DimensionalDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\State\FixedDecay;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Тик периодического урона: эффект, деление и decay.
 */
final class GameTurnTickDot
{
    /**
     * Создаёт тик урона.
     *
     * @param GameStrikeSplits $splits Деление.
     * @param ICharacterFormulaContexts $contexts Лист.
     * @param GameStrikeConsequences $consequences Проверки и урон.
     * @param GameTurnSheetStates $states Строки листа.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeSplits $splits,
        private readonly ICharacterFormulaContexts $contexts,
        private readonly GameStrikeConsequences $consequences,
        private readonly GameTurnSheetStates $states,
    ) {
    }

    /**
     * Операции одного состояния с периодическим уроном.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $row Строка состояния.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если сила состояния битая.
     */
    public function tick(GameRecord $game, CharacterRuleSlice $slice, array $sheet, array $row): array
    {
        $effect = $this->effectOf($slice, $row);
        if ($effect === null) {
            return [];
        }

        $period = $effect->getPeriodValue();
        $left = $this->remaining($row, $period);
        if ($left['wait'] > 0) {
            return [['kind' => 'patchState', 'id' => $row['id'], 'dotTurnsLeft' => $left['wait']]];
        }

        $injury = $this->injuryOf($effect, $row);
        $split = $this->splits->operation($slice, $this->contexts->build($sheet), $injury);
        $effects = $this->consequences->effects($game, $sheet, $injury, 0, $split, $effect->getDamageTypeCode(), false);
        $operations = $effects['operations'];
        $operations[] = $this->after($effect, $row, $left['left'], $period);

        return $operations;
    }

    /**
     * Строка после тика: снятие или новое значение.
     *
     * @param DamageOverTimeEffect $effect Эффект.
     * @param array<string, mixed> $row Строка.
     * @param int|null $left Остаток периодичности.
     * @param int|null $period Период.
     *
     * @return array<string, mixed> Операция.
     */
    private function after(DamageOverTimeEffect $effect, array $row, ?int $left, ?int $period): array
    {
        $next = $this->decay($effect, $row);
        if ($this->states->strength($next) <= 0) {
            return ['kind' => 'removeState', 'id' => $row['id']];
        }

        $patch = ['kind' => 'patchState', 'id' => $row['id'], 'value' => $next['value']];
        if ($period !== null && $period > 1) {
            $patch['dotTurnsLeft'] = $left;
        }

        return $patch;
    }

    /**
     * Эффект периодического урона, готовый к тику.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $row Строка.
     *
     * @return DamageOverTimeEffect|null Эффект или null.
     */
    private function effectOf(CharacterRuleSlice $slice, array $row): ?DamageOverTimeEffect
    {
        $rule = $slice->findLive($row['stateRuleCode']);
        $spec = $rule instanceof CharacterResolvedRule && !$rule->isSpecBroken() ? $rule->getSpec() : null;

        return $spec instanceof StateSpec ? $this->validEffect($spec) : null;
    }

    /**
     * Проверенный эффект урона.
     *
     * @param StateSpec $spec Состояние.
     *
     * @return DamageOverTimeEffect|null Эффект или null.
     */
    private function validEffect(StateSpec $spec): ?DamageOverTimeEffect
    {
        $effect = null;
        foreach ($spec->getEffects() as $candidate) {
            if ($candidate instanceof DamageOverTimeEffect) {
                $effect = $candidate;
            }
        }

        if ($effect === null) {
            return null;
        }

        $step = $effect->getPeriodStep();
        $stepOk = $step === null || $step === '' || $step === 'turn';
        $type = $effect->getDamageTypeCode();

        return $stepOk && $type !== null && $type !== '' ? $effect : null;
    }

    /**
     * Счётчик периодичности после текущего хода.
     *
     * @param array<string, mixed> $row Строка.
     * @param int|null $period Период.
     *
     * @return array{wait: int, left: int|null} Сколько ждать и остаток.
     */
    private function remaining(array $row, ?int $period): array
    {
        $left = is_int($row['dotTurnsLeft'] ?? null) ? $row['dotTurnsLeft'] : $period;
        if ($period === null || $period <= 1) {
            return ['wait' => 0, 'left' => $left];
        }

        $left = ($left ?? $period) - 1;

        return $left > 0 ? ['wait' => $left, 'left' => $left] : ['wait' => 0, 'left' => $period];
    }

    /**
     * Строка после decay.
     *
     * @param DamageOverTimeEffect $effect Эффект.
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Строка после decay.
     */
    private function decay(DamageOverTimeEffect $effect, array $row): array
    {
        $decay = $effect->getDecay();
        $strength = $this->states->strength($row);
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
     * Урон эффекта как число.
     *
     * @param DamageOverTimeEffect $effect Эффект.
     * @param array<string, mixed> $row Строка.
     *
     * @return DimensionalNumber Урон.
     *
     * @throws GameInvalidException Если значение состояния битое.
     */
    private function injuryOf(DamageOverTimeEffect $effect, array $row): DimensionalNumber
    {
        if ($effect->getDamageKind() === 'value') {
            $value = $row['value'] ?? 0;
            if (is_array($value)) {
                $base = $value['base'] ?? null;
                $size = $value['size'] ?? null;
                if (!is_int($base) || !is_int($size)) {
                    throw new GameInvalidException('Game turn state is invalid');
                }

                return new DimensionalNumber($base, $size);
            }

            return new DimensionalNumber(is_int($value) ? $value : 0, 0);
        }

        return new DimensionalNumber($effect->getAmount(), 0);
    }
}
