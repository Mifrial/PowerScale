<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход создания поставки механики.
 */
final class CreateMechanicInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param string $code Код семейства.
     * @param string $name Подпись.
     * @param string $version Поставка контракта.
     * @param string $description Текст.
     *
     * @return void
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description = '',
    ) {
    }
}
