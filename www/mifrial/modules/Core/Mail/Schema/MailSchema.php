<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Schema;

use Mifrial\Core\Mail\Table\MailEventTable;
use Mifrial\Core\Mail\Table\MailJobTable;
use Mifrial\Core\Mail\Table\MailTemplateTable;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Сверка карт Mail с физикой.
 */
final class MailSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $eventSchema DDL `mail_event`.
     * @param IOpenedSchema $templateSchema DDL `mail_template`.
     * @param IOpenedSchema $jobSchema DDL `mail_job`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $eventSchema,
        private readonly IOpenedSchema $templateSchema,
        private readonly IOpenedSchema $jobSchema,
    ) {
    }

    /**
     * Карты модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            MailEventTable::class,
            MailTemplateTable::class,
            MailJobTable::class,
        ];
    }

    /**
     * Создаёт или обновляет таблицы.
     *
     * @return void
     */
    public function install(): void
    {
        $this->apply($this->eventSchema);
        $this->apply($this->templateSchema);
        $this->apply($this->jobSchema);
    }

    /**
     * Одна карта.
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
