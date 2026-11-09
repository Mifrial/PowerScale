<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
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
     * @param array{
     *     difficulty: array{base: int, size: int},
     *     needsCheck: bool,
     *     roll: array<string, mixed>|null,
     *     castOk: bool
     * } $check Проверка.
     * @param array{
     *     spellSr: int|null,
     *     spellDamage: DimensionalNumber|null,
     *     injury: DimensionalNumber|null,
     *     operations: list<array<string, mixed>>,
     *     consequence: array<string, mixed>
     * } $damage Урон.
     * @param array{casterVersion: int, targetVersion: int|null} $versions Версии.
     *
     * @return array<string, mixed> Итог.
     */
    public function build(
        GameSpellCastPrepared $prepared,
        array $check,
        array $damage,
        array $versions,
    ): array {
        return [
            'cast' => [
                'difficulty' => $check['difficulty'],
                'needsCheck' => $check['needsCheck'],
                'roll' => $check['roll'],
                'upgrades' => $this->upgrades($prepared),
            ],
            'spent' => $prepared->spent,
            'caster' => [
                'type' => $prepared->casterKind,
                'id' => $prepared->casterId,
                'sheetVersion' => $versions['casterVersion'],
            ],
            'target' => $prepared->target === null
                ? null
                : [
                    'type' => $prepared->target['type'],
                    'id' => $prepared->target['id'],
                    'sheetVersion' => $versions['targetVersion'],
                ],
            'autoFail' => false,
            'spellSr' => $damage['spellSr'],
            'spellDamage' => $this->pair($damage['spellDamage']),
            'injury' => $this->pair($damage['injury']),
            'consequence' => $damage['consequence'],
        ];
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
