<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вторая учётка private-чата; актор — первый.
 */
final class AddPrivateChatInput implements IActionInput
{
    /**
     * Собирает вход addPrivate.
     *
     * @param int $userId Вторая учётка.
     *
     * @return void
     */
    public function __construct(
        public readonly int $userId,
    ) {
    }
}
