<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход создания мира.
 */
final class CreateSpaceInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param string $name Подпись.
     * @param string $description Текст.
     * @param int|null $inheritFrom Родитель.
     * @param string|null $code Ключ URL.
     *
     * @return void
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly ?int $inheritFrom = null,
        public readonly ?string $code = null,
    ) {
    }
}
