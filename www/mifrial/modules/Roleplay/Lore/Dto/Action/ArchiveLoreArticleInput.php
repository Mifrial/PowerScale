<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `lore.archiveArticle`.
 */
final class ArchiveLoreArticleInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $worldCode Код мира.
     * @param string $articleCode Код статьи.
     * @param bool $archived Архив.
     * @param OptionalInt $expectedVersion Версия клиента.
     *
     * @return void
     */
    public function __construct(
        public readonly string $worldCode,
        public readonly string $articleCode,
        public readonly bool $archived,
        public readonly OptionalInt $expectedVersion,
    ) {
    }
}
