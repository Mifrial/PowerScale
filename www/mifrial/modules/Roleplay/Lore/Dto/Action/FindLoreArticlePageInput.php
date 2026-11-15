<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Вход `lore.findArticlePage`.
 */
final class FindLoreArticlePageInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $worldCode Код мира.
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     * @param OptionalString $q Подстрока.
     * @param OptionalString $sectionCode Раздел.
     * @param OptionalString $status Статус.
     * @param OptionalString $visibility Видимость.
     * @param OptionalString $sort Order или recent.
     * @param array<int, mixed> $keywordIds Признаки.
     * @param bool $includeArchived Показывать архивные.
     *
     * @return void
     */
    public function __construct(
        public readonly string $worldCode,
        public readonly int $limit,
        public readonly int $offset,
        public readonly OptionalString $q,
        public readonly OptionalString $sectionCode,
        public readonly OptionalString $status,
        public readonly OptionalString $visibility,
        public readonly OptionalString $sort,
        public readonly array $keywordIds = [],
        public readonly bool $includeArchived = false,
    ) {
    }
}
