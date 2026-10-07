<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Поля JSON update, не порты DI.

namespace Mifrial\Roleplay\Game\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход game.update. Те же поля, что create, плюс id.
 */
final class UpdateGameInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Игра.
     * @param string $name Имя.
     * @param string $status Статус.
     * @param string $visibility Видимость.
     * @param string $joinPolicy Политика входа.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Номер ревизии.
     * @param int|null $osPointsLimit Потолок ОС.
     * @param int|null $olPointsLimit Потолок ОЛ.
     * @param int|null $orPointsLimit Потолок ОР.
     * @param int|null $moneyLimit Потолок денег.
     * @param string|null $shortDescription Кратко или null.
     * @param string|null $description Текст или null.
     * @param string|null $spaceCode Сверка или null.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $status,
        public readonly string $visibility,
        public readonly string $joinPolicy,
        public readonly int $spaceId,
        public readonly int $rulesRevision,
        public readonly ?int $osPointsLimit,
        public readonly ?int $olPointsLimit,
        public readonly ?int $orPointsLimit,
        public readonly ?int $moneyLimit,
        public readonly ?string $shortDescription = null,
        public readonly ?string $description = null,
        public readonly ?string $spaceCode = null,
    ) {
    }
}
