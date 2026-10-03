<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля JSON migrate, не порты DI.

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход character.migrate. Мир строки не принимается. Тело листа отличает продолжение от ремапа.
 */
final class MigrateCharacterInput implements IActionInput
{
    /**
     * Собирает вход миграции.
     *
     * @param int $id Персонаж.
     * @param int $revision Целевая ревизия.
     * @param int|null $expectedVersion actual_version или null, если ключа нет.
     * @param OptionalString $name Имя.
     * @param OptionalArray $limits Потолки.
     * @param OptionalString $raceCode Раса.
     * @param OptionalInt $money Наличные.
     * @param OptionalString $shortDescription Кратко.
     * @param OptionalString $fullDescription Текст.
     * @param OptionalInt $ageYears Возраст.
     * @param OptionalArray $characteristicPurchases Закупки.
     * @param OptionalArray $abilities Способности.
     * @param OptionalArray $inventory Инвентарь.
     * @param OptionalArray $customRules Свои правила.
     * @param OptionalBool $active Флаг.
     * @param OptionalArray $expectedSheet Сверка.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly int $revision,
        public readonly ?int $expectedVersion,
        public readonly OptionalString $name,
        public readonly OptionalArray $limits,
        public readonly OptionalString $raceCode,
        public readonly OptionalInt $money,
        public readonly OptionalString $shortDescription,
        public readonly OptionalString $fullDescription,
        public readonly OptionalInt $ageYears,
        public readonly OptionalArray $characteristicPurchases,
        public readonly OptionalArray $abilities,
        public readonly OptionalArray $inventory,
        public readonly OptionalArray $customRules,
        public readonly OptionalBool $active,
        public readonly OptionalArray $expectedSheet,
    ) {
    }
}
