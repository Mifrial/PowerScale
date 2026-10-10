<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ConditionalCas;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\RowWriteFailedException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IConditionalOpenedRecords;
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

    private readonly IConditionalOpenedRecords $conditionalCharacterRecords;

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
        $openedTable = $smartTableGateway->open(CharacterTable::class);
        $this->characterRecords = $openedTable->records();
        $this->conditionalCharacterRecords = $openedTable->conditionalRecords();
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
            function () use ($choices, $sheet, $updatedAt): array {
                return [
                    'choices' => $choices,
                    'sheet' => $sheet,
                    'updated_at' => $updatedAt,
                ];
            },
        );
    }

    /**
     * Пишет имя, active, choices и sheet, если version совпал.
     *
     * @param int $characterId Идентификатор.
     * @param string $name Имя.
     * @param bool $active Флаг.
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
    public function replaceSaved(
        int $characterId,
        string $name,
        bool $active,
        array $choices,
        array $sheet,
        int $expectedVersion,
        DateTime $updatedAt,
    ): CharacterRecord {
        return $this->writeGuarded(
            $characterId,
            $expectedVersion,
            function () use ($name, $active, $choices, $sheet, $updatedAt): array {
                return [
                    'name' => $name,
                    'active' => $active,
                    'choices' => $choices,
                    'sheet' => $sheet,
                    'updated_at' => $updatedAt,
                ];
            },
        );
    }

    /**
     * Пишет лист и номер ревизии, если version совпал.
     *
     * @param int $characterId Идентификатор.
     * @param string $name Имя.
     * @param bool $active Флаг.
     * @param array $choices Build.
     * @param array $sheet Кэш.
     * @param int $rulesRevision Номер ревизии.
     * @param int $expectedVersion Lock.
     * @param DateTime $updatedAt Момент записи.
     *
     * @return CharacterRecord После update.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     * @throws CharacterInvalidException Если поле.
     */
    public function replaceMigrated(
        int $characterId,
        string $name,
        bool $active,
        array $choices,
        array $sheet,
        int $rulesRevision,
        int $expectedVersion,
        DateTime $updatedAt,
    ): CharacterRecord {
        return $this->writeGuarded(
            $characterId,
            $expectedVersion,
            function () use ($name, $active, $choices, $sheet, $rulesRevision, $updatedAt): array {
                return [
                    'name' => $name,
                    'active' => $active,
                    'choices' => $choices,
                    'sheet' => $sheet,
                    'rules_revision' => $rulesRevision,
                    'updated_at' => $updatedAt,
                ];
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
            function () use ($active, $updatedAt): array {
                return [
                    'active' => $active,
                    'updated_at' => $updatedAt,
                ];
            },
        );
    }

    /**
     * TX: сравнить version, bump, выполнить write.
     *
     * @param int $characterId Идентификатор.
     * @param int $expectedVersion Lock.
     * @param Closure(): array<string, mixed> $writer Поля без actual_version.
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
                $updated = $this->updateConditional($characterId, $expectedVersion, $writer());
                if ($updated) {
                    return $this->getById($characterId);
                }

                $row = $this->conditionalCharacterRecords->getCurrentById($characterId);
                if ($row === null) {
                    throw new CharacterNotFoundException('Character was not found');
                }

                throw new CharacterConflictException(
                    (int) $row['actual_version'],
                    currentSheet: $this->sheetOf($row),
                );
            },
        );
    }

    /**
     * Conditional update колонок.
     *
     * @param int $characterId Идентификатор.
     * @param int $expectedVersion Ожидаемый actual_version.
     * @param array<string, mixed> $fields Колонки без счётчика.
     *
     * @return bool true, если строка записана.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если поле.
     */
    private function updateConditional(int $characterId, int $expectedVersion, array $fields): bool
    {
        try {
            return $this->conditionalCharacterRecords->updateConditional(
                $characterId,
                new ConditionalCas('actual_version', $expectedVersion),
                $fields,
            );
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

    /**
     * Лист из свежей строки.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array{choices: array<mixed>, sheet: array<mixed>} Лист.
     */
    private function sheetOf(array $row): array
    {
        $choices = $row['choices'] ?? [];
        $sheet = $row['sheet'] ?? [];

        return [
            'choices' => is_array($choices) ? $choices : [],
            'sheet' => is_array($sheet) ? $sheet : [],
        ];
    }
}
