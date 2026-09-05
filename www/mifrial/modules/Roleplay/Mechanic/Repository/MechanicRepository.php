<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;

/**
 * Строки `mechanic`.
 */
final class MechanicRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $mechanicRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $mechanicRecords,
    ) {
    }

    /**
     * Добавляет строку.
     *
     * @param string $code Уже trim, непустой.
     * @param string $name Уже trim, непустой.
     * @param string $description Текст.
     * @param string $handlerVersion Уже trim, непустой.
     *
     * @return int Id.
     *
     * @throws MechanicInvalidException Если поле, карта или unique.
     */
    public function add(string $code, string $name, string $description, string $handlerVersion): int
    {
        try {
            return $this->mechanicRecords->add([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'handler_version' => $handlerVersion,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new MechanicInvalidException('Mechanic code and handler version are already used', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new MechanicInvalidException('Mechanic field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $mechanicId Идентификатор.
     *
     * @return MechanicRecord Механика.
     *
     * @throws MechanicNotFoundException Если строки нет.
     * @throws MechanicInvalidException Если Record неполный.
     */
    public function getById(int $mechanicId): MechanicRecord
    {
        $row = $this->mechanicRecords->getById($mechanicId);
        if ($row === null) {
            throw new MechanicNotFoundException();
        }

        return MechanicRecord::fromNormalized($row);
    }

    /**
     * Строка по семейству и поставке.
     *
     * @param string $code Уже trim, непустой.
     * @param string $handlerVersion Уже trim, непустой.
     *
     * @return MechanicRecord Механика.
     *
     * @throws MechanicNotFoundException Если строки нет.
     * @throws MechanicInvalidException Если Record неполный.
     */
    public function getByCodeVersion(string $code, string $handlerVersion): MechanicRecord
    {
        $row = $this->mechanicRecords->getUnique(ListQuery::fromOptions([
            'filter' => [
                'code' => $code,
                'handler_version' => $handlerVersion,
            ],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new MechanicNotFoundException();
        }

        return MechanicRecord::fromNormalized($row);
    }

    /**
     * Все поставки, sort id ASC.
     *
     * @return list<MechanicRecord> Каталог.
     *
     * @throws MechanicInvalidException Если выборка битая.
     */
    public function getList(): array
    {
        try {
            $listResult = $this->mechanicRecords->getList(ListQuery::fromOptions([
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new MechanicInvalidException('Mechanic list is invalid', $exception);
        }

        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = MechanicRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет переданные поля.
     *
     * @param int $mechanicId Идентификатор.
     * @param array<string, mixed> $fields Колонки без code и handler_version.
     *
     * @return void
     *
     * @throws MechanicNotFoundException Если строки нет.
     * @throws MechanicInvalidException Если поле.
     */
    public function update(int $mechanicId, array $fields): void
    {
        try {
            $this->mechanicRecords->update($mechanicId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new MechanicNotFoundException('Mechanic was not found', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new MechanicInvalidException('Mechanic field is invalid', $exception);
        }
    }
}
