<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;

/**
 * Вход правки мета мира.
 */
final class UpdateSpaceInput implements IActionInput
{
    /**
     * Собирает вход update.
     *
     * @param int $id Мир.
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
