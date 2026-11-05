<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Плоский JSON findPage: limit/offset и фильтры — поля входа, не порты.

namespace Mifrial\Core\File\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход file.findPage: страница и плоские фильтры.
 */
final class FindFilePageInput implements IActionInput
{
    /**
     * Собирает вход страницы.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $mime Тип.
     * @param OptionalInt $ownerId Владелец.
     * @param OptionalBool $isArchived Архив.
     * @param OptionalBool $isDerived Производная.
     * @param int|null $from Нижняя граница unix UTC.
     * @param int|null $to Верхняя граница unix UTC.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $mime,
        public readonly OptionalInt $ownerId,
        public readonly OptionalBool $isArchived,
        public readonly OptionalBool $isDerived,
        public readonly ?int $from = null,
        public readonly ?int $to = null,
    ) {
    }
}
