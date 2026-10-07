<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Interface\Service;

use Mifrial\Messages\Chat\Exception\ChatInvalidException;

/**
 * Память процесса: непрозрачные строки типов донора.
 */
interface IChatTypeRegistry
{
    /**
     * Запоминает строку типа.
     *
     * @param string $type Строка донора.
     *
     * @return void
     *
     * @throws ChatInvalidException Если пусто или это private/group.
     */
    public function register(string $type): void;

    /**
     * Строка уже зарегистрирована.
     *
     * @param string $type Строка донора.
     *
     * @return bool true, если register этой строки уже прошёл.
     */
    public function isRegistered(string $type): bool;
}
