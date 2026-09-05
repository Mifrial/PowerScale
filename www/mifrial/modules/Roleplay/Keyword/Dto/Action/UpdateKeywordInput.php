<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход правки признака.
 */
final class UpdateKeywordInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Признак.
     * @param OptionalString $name Подпись.
     * @param OptionalString $description Текст.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly OptionalString $name,
        public readonly OptionalString $description,
    ) {
    }
}
