<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход character.updateVisibility. is_public клиент не шлёт.
 */
final class UpdateCharacterVisibilityInput implements IActionInput
{
    /**
     * Собирает вход видимости.
     *
     * @param int $id Персонаж.
     * @param array $visibilityFields Секции всем.
     * @param array $viewers Зрители.
     *
     * @return void
     */
    public function __construct(
        public readonly int $id,
        public readonly array $visibilityFields,
        public readonly array $viewers,
    ) {
    }
}
