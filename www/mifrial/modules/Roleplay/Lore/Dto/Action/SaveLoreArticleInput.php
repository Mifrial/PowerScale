<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;

/**
 * Вход `lore.saveArticle`.
 */
final class SaveLoreArticleInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $worldCode Код мира.
     * @param array<string|int, mixed> $article Полный снимок.
     * @param OptionalInt $expectedVersion Версия клиента; нет поля — создание.
     *
     * @return void
     */
    public function __construct(
        public readonly string $worldCode,
        public readonly array $article,
        public readonly OptionalInt $expectedVersion,
    ) {
    }
}
