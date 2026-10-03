<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход character.get.
 */
final class GetCharacterInput implements IActionInput
{
    /**
     * Собирает вход чтения.
     *
     * @param int $id Персонаж.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
    ) {
    }
}
