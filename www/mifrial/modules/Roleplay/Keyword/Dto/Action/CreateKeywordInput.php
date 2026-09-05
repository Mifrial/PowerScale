<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Dto\Action;

use Mifrial\Core\Kernel\Interface\Action\IActionInput;

/**
 * Вход создания признака.
 */
final class CreateKeywordInput implements IActionInput
{
    /**
     * Собирает вход create.
     *
     * @param string $code Семантический ключ.
     * @param string $name Подпись.
     * @param string $description Текст.
     *
     * @return void
     */
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $description = '',
    ) {
    }
}
