<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationResult;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ответ каста (game.runAction kind spell; game.castSpell — алиас).
 */
final class GameSpellCastView
{
    /**
     * Собирает итог каста.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     * @param array<string, mixed> $check Проверка.
     * @param array<string, mixed> $damage Урон.
     * @param array{casterVersion: int, targetVersion: int|null} $versions Версии.
     * @param string $phase Фаза check или resolve.
     * @param string|null $castId Ключ каста.
     * @param GameSpellCastSaturationPlan|null $plan Выбор насыщения.
     * @param GameSpellCastSaturationResult|null $saturation Итог насыщения.
     * @param array<int, array<string, mixed>> $nextCast Поставленные хвосты.
     *
     * @return array<string, mixed> Итог.
     */
    public function build(
        GameSpellCastPrepared $prepared,
        array $check,
        array $damage,
        array $versions,
        string $phase = 'resolve',
        ?string $castId = null,
        ?GameSpellCastSaturationPlan $plan = null,
        ?GameSpellCastSaturationResult $saturation = null,
        array $nextCast = [],
    ): array {
        $target = $prepared->damageTarget ?? $prepared->target;

        return [
            'phase' => $phase,
            'castId' => $castId,
            'cast' => [
                'difficulty' => $check['difficulty'],
                'needsCheck' => $check['needsCheck'],
                'roll' => $check['roll'],
                'castOk' => $check['castOk'],
                'upgrades' => $this->upgrades($prepared),
            ],
            'saturation' => $this->saturationOf($plan, $saturation),
            'nextCast' => $nextCast,
            'spent' => $prepared->spent,
            'caster' => [
                'type' => $prepared->casterKind,
                'id' => $prepared->casterId,
                'sheetVersion' => $versions['casterVersion'],
            ],
            'target' => $target === null
                ? null
                : [
                    'type' => $target['type'],
                    'id' => $target['id'],
                    'sheetVersion' => $versions['targetVersion'] ?? $target['version'],
                ],
            'autoFail' => false,
            'spellSr' => $damage['spellSr'],
            'spellDamage' => $this->pair($damage['spellDamage']),
            'injury' => $this->pair($damage['injury']),
            'consequence' => $damage['consequence'],
        ];
    }

    /**
     * Блок насыщения для ответа.
     *
     * @param GameSpellCastSaturationPlan|null $plan Выбор.
     * @param GameSpellCastSaturationResult|null $result Итог.
     *
     * @return array<string, mixed>|null Блок или null.
     */
    private function saturationOf(
        ?GameSpellCastSaturationPlan $plan,
        ?GameSpellCastSaturationResult $result,
    ): ?array {
        if ($result !== null) {
            $source = $result->getSource();
            if ($source === null) {
                return null;
            }

            return [
                'sourceCode' => $source->getRuleCode(),
                'steps' => $result->getSteps(),
                'ratingSpent' => $result->getRatingSpent(),
                'powerDelta' => $result->getPowerDelta(),
                'remainingRating' => $result->getRemainingRating(),
            ];
        }

        if ($plan === null) {
            return null;
        }

        return $plan->toArray();
    }

    /**
     * Применённые улучшения для ответа.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return list<array{
     *     code: string,
     *     name: string,
     *     value: int,
     *     actionPointDelta: int,
     *     checkAdvantage: int,
     *     resistancePenetration: int,
     *     requiredPowerDelta: int
     * }> Улучшения.
     */
    private function upgrades(GameSpellCastPrepared $prepared): array
    {
        $rows = [];
        foreach ($prepared->upgrades->getApplied() as $upgrade) {
            $rows[] = [
                'code' => $upgrade->getRuleCode(),
                'name' => $upgrade->getName(),
                'value' => $upgrade->getValue(),
                'actionPointDelta' => $upgrade->getActionPointDelta(),
                'checkAdvantage' => $upgrade->getCheckAdvantage(),
                'resistancePenetration' => $upgrade->getResistancePenetration(),
                'requiredPowerDelta' => $upgrade->getRequiredPowerDelta(),
            ];
        }

        return $rows;
    }

    /**
     * Размерная пара или null.
     *
     * @param DimensionalNumber|null $number Число.
     *
     * @return array{base: int, size: int}|null Пара.
     */
    private function pair(?DimensionalNumber $number): ?array
    {
        if (!$number instanceof DimensionalNumber) {
            return null;
        }

        return ['base' => $number->getBase(), 'size' => $number->getSize()];
    }
}
