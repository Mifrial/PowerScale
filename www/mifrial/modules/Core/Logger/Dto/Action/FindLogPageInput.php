<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Плоский JSON findPage: limit/offset и фильтры — поля входа, не порты.

namespace Mifrial\Core\Logger\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход logger.findPage: страница и плоские фильтры.
 */
final class FindLogPageInput implements IActionInput
{
    /**
     * Собирает вход страницы.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $level Уровень.
     * @param OptionalString $source Источник.
     * @param OptionalString $sourceMode Equals или contains.
     * @param OptionalString $errorCode Код ошибки.
     * @param OptionalString $errorCodeMode Equals или contains.
     * @param int|null $from Нижняя граница unix UTC.
     * @param int|null $to Верхняя граница unix UTC.
     *
     * @return void
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $level,
        public readonly OptionalString $source,
        public readonly OptionalString $sourceMode,
        public readonly OptionalString $errorCode,
        public readonly OptionalString $errorCodeMode,
        public readonly ?int $from = null,
        public readonly ?int $to = null,
    ) {
    }
}
