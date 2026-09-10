<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Table\CharacterTable;

/**
 * Строки `character`.
 */
final class CharacterRepository
{
    private readonly IOpenedRecords $characterRecords;

    /**
     * Создаёт репозиторий.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз ST.
     *
     * @return void
     */
    public function __construct(
        private readonly ISmartTableGateway $smartTableGateway,
    ) {
        $this->characterRecords = $smartTableGateway->open(CharacterTable::class)->records();
    }

    /**
     * Вставляет строку.
     *
     * @param array<string, mixed> $values Колонки без id.
     *
     * @return int Id.
     *
     * @throws CharacterNotFoundException Если нет часов.
     * @throws CharacterInvalidException Если поле.
     */
    public function add(array $values): int
    {
        try {
            return $this->characterRecords->add($values);
        } catch (ReferenceConstraintException $exception) {
            throw new CharacterNotFoundException('Character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new CharacterInvalidException('Character field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $characterId Идентификатор.
     *
     * @return CharacterRecord Персонаж.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если Record неполный.
     */
    public function getById(int $characterId): CharacterRecord
    {
        $row = $this->characterRecords->getById($characterId);
        if ($row === null) {
            throw new CharacterNotFoundException();
        }

        return CharacterRecord::fromNormalized($row);
    }

    /**
     * Пишет choices и sheet, если version совпал.
     *
     * @param int $characterId Идентификатор.
     * @param array $choices Build.
     * @param array $sheet Кэш.
     * @param int $expectedVersion Lock.
     * @param DateTime $updatedAt Момент записи.
     *
     * @return CharacterRecord После update.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     * @throws CharacterInvalidException Если поле.
     */
    public function replacePayload(
        int $characterId,
        array $choices,
        array $sheet,
        int $expectedVersion,
        DateTime $updatedAt,
    ): CharacterRecord {
        return $this->writeGuarded(
            $characterId,
            $expectedVersion,
            function (int $nextVersion) use ($characterId, $choices, $sheet, $updatedAt): void {
                $this->updateRow($characterId, [
                    'choices' => $choices,
                    'sheet' => $sheet,
                    'actual_version' => $nextVersion,
                    'updated_at' => $updatedAt,
                ]);
            },
        );
    }

    /**
     * Пишет active, если version совпал.
     *
     * @param int $characterId Идентификатор.
     * @param bool $active Флаг.
     * @param int $expectedVersion Lock.
     * @param DateTime $updatedAt Момент записи.
     *
     * @return CharacterRecord После update.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     * @throws CharacterInvalidException Если поле.
     */
    public function setActive(
        int $characterId,
        bool $active,
        int $expectedVersion,
        DateTime $updatedAt,
    ): CharacterRecord {
        return $this->writeGuarded(
            $characterId,
            $expectedVersion,
            function (int $nextVersion) use ($characterId, $active, $updatedAt): void {
                $this->updateRow($characterId, [
                    'active' => $active,
                    'actual_version' => $nextVersion,
                    'updated_at' => $updatedAt,
                ]);
            },
        );
    }

    /**
     * TX: сравнить version, bump, выполнить write.
     *
     * @param int $characterId Идентификатор.
     * @param int $expectedVersion Lock.
     * @param Closure $writer Update с next version.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     * @throws CharacterInvalidException Если поле.
     */
    private function writeGuarded(int $characterId, int $expectedVersion, Closure $writer): CharacterRecord
    {
        return $this->smartTableGateway->transaction(
            function () use ($characterId, $expectedVersion, $writer): CharacterRecord {
                $characterRecord = $this->getById($characterId);
                if ($characterRecord->getActualVersion() !== $expectedVersion) {
                    throw new CharacterConflictException($characterRecord->getActualVersion());
                }

                $writer($expectedVersion + 1);

                return $this->getById($characterId);
            },
        );
    }

    /**
     * Update колонок.
     *
     * @param int $characterId Идентификатор.
     * @param array<string, mixed> $fields Колонки.
     *
     * @return void
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если поле.
     */
    private function updateRow(int $characterId, array $fields): void
    {
        try {
            $this->characterRecords->update($characterId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new CharacterNotFoundException('Character was not found', $exception);
        } catch (
            FieldRequiredException
            | FieldInvalidException
            | MapInvalidException
            | UniqueConstraintException
            | RowWriteFailedException $exception
        ) {
            throw new CharacterInvalidException('Character field is invalid', $exception);
        }
    }
}
