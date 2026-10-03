<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля JSON update, не порты DI.

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход character.update. limits.money биндер не принимает: ключа в limits нет в белом списке сценария.
 */
final class UpdateCharacterInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Персонаж.
     * @param int $spaceId Мир строки.
     * @param int $revision Ревизия строки.
     * @param string $name Имя.
     * @param array $limits Потолки без money.
     * @param string $raceCode Раса.
     * @param OptionalInt $money Наличные.
     * @param int|null $expectedVersion actual_version или null, если ключа нет.
     * @param string $shortDescription Кратко.
     * @param string $fullDescription Текст.
     * @param int|null $ageYears Возраст.
     * @param array $characteristicPurchases Покупки характеристик.
     * @param array $abilities Способности.
     * @param array $inventory Инвентарь.
     * @param array $customRules Свои правила.
     * @param bool|null $active Флаг или null.
     * @param array|null $expectedSheet Сверка или null.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly int $spaceId,
        public readonly int $revision,
        public readonly string $name,
        public readonly array $limits,
        public readonly string $raceCode,
        public readonly OptionalInt $money,
        public readonly ?int $expectedVersion = null,
        public readonly string $shortDescription = '',
        public readonly string $fullDescription = '',
        public readonly ?int $ageYears = null,
        public readonly array $characteristicPurchases = [],
        public readonly array $abilities = [],
        public readonly array $inventory = [],
        public readonly array $customRules = [],
        public readonly ?bool $active = null,
        public readonly ?array $expectedSheet = null,
    ) {
    }
}
