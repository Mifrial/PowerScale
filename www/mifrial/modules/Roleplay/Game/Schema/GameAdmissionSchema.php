<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Game\Table\GameInvitationTable;
use Mifrial\Roleplay\Game\Table\GameJoinRequestTable;

/**
 * Карты приглашения и заявки.
 */
final class GameAdmissionSchema
{
    /**
     * Создаёт установщик.
     *
     * @param IOpenedSchema $invitationSchema DDL приглашения.
     * @param IOpenedSchema $joinRequestSchema DDL заявки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $invitationSchema,
        private readonly IOpenedSchema $joinRequestSchema,
    ) {
    }

    /**
     * Возвращает class-string карт.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            GameInvitationTable::class,
            GameJoinRequestTable::class,
        ];
    }

    /**
     * Приводит таблицы к текущим картам.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->invitationSchema);
        $this->apply($this->joinRequestSchema);
    }

    /**
     * Создаёт или обновляет одну карту.
     *
     * @param IOpenedSchema $openedSchema DDL.
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
