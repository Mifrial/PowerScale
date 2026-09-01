<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Mifrial\Core\SmartTable\Field\BaseField;
use Mifrial\Core\SmartTable\Table\SmartTableDefinition;

/**
 * Запись и чтение множеств mfv.
 */
final class MfvRows
{
    /**
     * Создаёт доступ к mfv.
     *
     * @param IlluminateDatabaseConnection $databaseConnection Адаптер.
     * @param DriverErrorTranslator $driverErrors Переводчик SQLSTATE.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateDatabaseConnection $databaseConnection,
        private readonly DriverErrorTranslator $driverErrors,
    ) {
    }

    /**
     * Заменяет множество поля.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param BaseField $field Multiple-поле.
     * @param int $ownerId Id строки.
     * @param array<int, mixed> $extractedValues Значения extract.
     *
     * @return void
     */
    public function replace(
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        int $ownerId,
        array $extractedValues,
    ): void {
        $physicalName = MfvSchema::tableName($tableDefinition, $field);
        $this->driverErrors->run(function () use ($physicalName, $ownerId, $extractedValues): void {
            $connection = $this->databaseConnection->illuminateConnection();
            $connection->table($physicalName)->where('owner_id', $ownerId)->delete();
            foreach ($extractedValues as $extractedValue) {
                $connection->table($physicalName)->insert([
                    'owner_id' => $ownerId,
                    'value' => $extractedValue,
                ]);
            }
        });
    }

    /**
     * Удаляет все mfv владельца.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param int $ownerId Id строки.
     *
     * @return void
     */
    public function deleteByOwner(SmartTableDefinition $tableDefinition, int $ownerId): void
    {
        foreach ($tableDefinition->getMap() as $field) {
            if (!$field->settings()->multiple()) {
                continue;
            }

            $physicalName = MfvSchema::tableName($tableDefinition, $field);
            $this->driverErrors->run(function () use ($physicalName, $ownerId): void {
                $this->databaseConnection->illuminateConnection()
                    ->table($physicalName)
                    ->where('owner_id', $ownerId)
                    ->delete();
            });
        }
    }

    /**
     * Грузит значения по списку id.
     *
     * @param SmartTableDefinition $tableDefinition Определение.
     * @param BaseField $field Multiple-поле.
     * @param array<int, int> $ownerIds Id строк.
     *
     * @return array<int, array<int, mixed>> owner_id => list value.
     */
    public function loadByOwners(
        SmartTableDefinition $tableDefinition,
        BaseField $field,
        array $ownerIds,
    ): array {
        $groupedValues = array_fill_keys($ownerIds, []);
        if ($ownerIds === []) {
            return $groupedValues;
        }

        $physicalName = MfvSchema::tableName($tableDefinition, $field);
        $databaseRows = $this->driverErrors->run(
            fn (): array => $this->databaseConnection->illuminateConnection()
                ->table($physicalName)
                ->whereIn('owner_id', $ownerIds)
                ->orderBy('value')
                ->get()
                ->all(),
        );
        foreach ($databaseRows as $databaseRow) {
            $rowMap = is_object($databaseRow) ? get_object_vars($databaseRow) : (array) $databaseRow;
            $ownerId = (int) $rowMap['owner_id'];
            $groupedValues[$ownerId][] = $rowMap['value'];
        }

        return $groupedValues;
    }
}
