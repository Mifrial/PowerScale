<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля JSON create, не порты DI.

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход character.create. Наличных money и expectedVersion нет.
 */
final class CreateCharacterInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param int $spaceId Мир.
     * @param int $revision Ревизия.
     * @param string $name Имя.
     * @param array $limits Потолки.
     * @param string $raceCode Раса.
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
        public readonly int $spaceId,
        public readonly int $revision,
        public readonly string $name,
        public readonly array $limits,
        public readonly string $raceCode,
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
