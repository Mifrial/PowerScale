<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Service;

use Mifrial\Messages\Chat\Dto\ChatHostTypes;
use Mifrial\Messages\Chat\Exception\ChatInvalidException;
use Mifrial\Messages\Chat\Interface\Service\IChatTypeRegistry;

/**
 * Набор строк типов на один контейнер. Не каталог имён донора.
 */
final class ChatTypeRegistry implements IChatTypeRegistry
{
    /**
     * @var array<string, true>
     */
    private array $types = [];

    /**
     * Запоминает строку. Повтор той же строки ничего не меняет.
     *
     * @param string $type Строка донора.
     *
     * @return void
     *
     * @throws ChatInvalidException Если пусто или это private/group.
     */
    public function register(string $type): void
    {
        $normalizedType = trim($type);
        if ($normalizedType === '' || ChatHostTypes::isHost($normalizedType)) {
            throw new ChatInvalidException('Chat type cannot be registered');
        }

        $this->types[$normalizedType] = true;
    }

    /**
     * Строка уже зарегистрирована.
     *
     * @param string $type Строка донора.
     *
     * @return bool true, если register этой строки уже прошёл.
     */
    public function isRegistered(string $type): bool
    {
        return isset($this->types[trim($type)]);
    }
}
