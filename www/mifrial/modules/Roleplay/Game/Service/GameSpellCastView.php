<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Ответ game.castSpell.
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
