<?php

declare(strict_types=1);

namespace Mifrial\Messages\Chat\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Messages\Chat\Table\ChatMemberTable;
use Mifrial\Messages\Chat\Table\ChatMessageTable;
use Mifrial\Messages\Chat\Table\ChatTable;

/**
 * Сверка карт Chat с физикой. Не порт соседа.
 */
final class ChatSchema
{
    /**
     * Создаёт установщик схемы модуля.
     *
     * @param IOpenedSchema $chatSchema DDL `chat`.
     * @param IOpenedSchema $memberSchema DDL `chat_member`.
     * @param IOpenedSchema $messageSchema DDL `chat_message`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $chatSchema,
        private readonly IOpenedSchema $memberSchema,
        private readonly IOpenedSchema $messageSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля в зашитом порядке для тестов.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты Chat.
     */
    public static function getTableClasses(): array
    {
        return [
            ChatTable::class,
            ChatMemberTable::class,
            ChatMessageTable::class,
        ];
    }

    /**
     * Приводит таблицы модуля к текущим картам.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->chatSchema);
        $this->apply($this->memberSchema);
        $this->apply($this->messageSchema);
    }

    /**
     * Создаёт или обновляет одну карту.
     *
     * @param IOpenedSchema $openedSchema DDL одной таблицы.
     *
     * @return void
     */
    private function apply(IOpenedSchema $openedSchema): void
    {
        if ($openedSchema->exists()) {
            $openedSchema->updateTable();

            return;
        }

        $openedSchema->createTable();
    }
}
