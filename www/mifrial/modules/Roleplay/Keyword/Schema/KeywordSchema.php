<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Schema;

use Mifrial\Core\SmartTable\Interface\Service\IOpenedSchema;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;

/**
 * Сверка карты Keyword с физикой.
 */
final class KeywordSchema
{
    /**
     * Создаёт установщик схемы.
     *
     * @param IOpenedSchema $keywordSchema DDL `keyword`.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedSchema $keywordSchema,
    ) {
    }

    /**
     * Возвращает class-string карт модуля.
     *
     * @return array<int, class-string<SmartTableDefinition>> Карты.
     */
    public static function getTableClasses(): array
    {
        return [
            KeywordTable::class,
        ];
    }

    /**
     * Приводит таблицу к текущей карте.
     *
     * @return void
     */
    public function install(): void
    {
        if ($this->keywordSchema->exists()) {
            $this->keywordSchema->updateTable();

            return;
        }

        $this->keywordSchema->createTable();
    }
}
