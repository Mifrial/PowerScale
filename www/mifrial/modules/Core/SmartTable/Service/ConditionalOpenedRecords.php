<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Interface\Service\IConditionalOpenedRecords;
use Mifrial\Core\SmartTable\Service\Cache\TableCache;
use Mifrial\Core\SmartTable\Service\Query\ConditionalTableRows;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Условные операции строк одной открытой карты.
 */
final class ConditionalOpenedRecords implements IConditionalOpenedRecords
{
    /**
     * Создаёт conditional-порт строк.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param ConditionalTableRows $conditionalTableRows Условные строки.
     * @param TableCache $tableCache Кэш.
     *
     * @return void
     */
    public function __construct(
        private readonly SmartTableDefinition $tableDefinition,
        private readonly ConditionalTableRows $conditionalTableRows,
        private readonly TableCache $tableCache,
    ) {
    }

    /**
     * Пишет строку, если CAS совпал.
     *
     * @param int $rowId Идентификатор.
     * @param ConditionalCas $cas Поле и ожидаемое значение.
     * @param array<string, mixed> $values Поля к записи без CAS.
     *
     * @return bool true, если строка обновлена.
     */
    public function updateConditional(int $rowId, ConditionalCas $cas, array $values): bool
    {
        $updated = $this->conditionalTableRows->updateConditional(
            $this->tableDefinition,
            $rowId,
            $cas,
            $values,
        );
        if ($updated) {
            $this->tableCache->noteUpdate(
                $this->tableDefinition->getName(),
                $rowId,
                [...array_keys($values), $cas->casField],
            );
        }

        return $updated;
    }

    /**
     * Читает текущую строку с блокировкой, без кэша.
     *
     * @param int $rowId Идентификатор.
     *
     * @return array<string, mixed>|null Гидратированные поля или null.
     */
    public function getCurrentById(int $rowId): ?array
    {
        return $this->conditionalTableRows->getCurrentById($this->tableDefinition, $rowId);
    }
}
