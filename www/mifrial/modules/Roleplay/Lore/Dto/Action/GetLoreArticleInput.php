<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход `lore.getArticle`.
 */
final class GetLoreArticleInput implements IActionInput
{
    /**
     * Собирает вход.
     *
     * @param string $worldCode Код мира.
     * @param string $articleCode Код статьи.
     *
     * @return void
     */
    public function __construct(
        public readonly string $worldCode,
        public readonly string $articleCode,
    ) {
    }
}
