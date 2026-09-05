<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;

/**
 * Строки `keyword`.
 */
final class KeywordRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $keywordRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $keywordRecords,
    ) {
    }

    /**
     * Добавляет строку.
     *
     * @param string $code Уже trim, непустой.
     * @param string $name Уже trim, непустой.
     * @param string $description Текст.
     * @param bool $active Флаг.
     *
     * @return int Id.
     *
     * @throws KeywordInvalidException Если поле, карта или unique.
     */
    public function add(string $code, string $name, string $description, bool $active): int
    {
        try {
            return $this->keywordRecords->add([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'active' => $active,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new KeywordInvalidException('Keyword code is already used', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new KeywordInvalidException('Keyword field is invalid', $exception);
        }
    }

    /**
     * Строка по id.
     *
     * @param int $keywordId Идентификатор.
     *
     * @return KeywordRecord Признак.
     *
     * @throws KeywordNotFoundException Если строки нет.
     * @throws KeywordInvalidException Если Record неполный.
     */
    public function getById(int $keywordId): KeywordRecord
    {
        $row = $this->keywordRecords->getById($keywordId);
        if ($row === null) {
            throw new KeywordNotFoundException();
        }

        return KeywordRecord::fromNormalized($row);
    }

    /**
     * Строка по коду.
     *
     * @param string $code Уже trim, непустой.
     *
     * @return KeywordRecord Признак.
     *
     * @throws KeywordNotFoundException Если строки нет.
     * @throws KeywordInvalidException Если Record неполный.
     */
    public function getByCode(string $code): KeywordRecord
    {
        $row = $this->keywordRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['code' => $code],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new KeywordNotFoundException();
        }

        return KeywordRecord::fromNormalized($row);
    }

    /**
     * Все строки, sort id ASC.
     *
     * @return list<KeywordRecord> Каталог.
     *
     * @throws KeywordInvalidException Если выборка битая.
     */
    public function getList(): array
    {
        try {
            $listResult = $this->keywordRecords->getList(ListQuery::fromOptions([
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
            ]));
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new KeywordInvalidException('Keyword list is invalid', $exception);
        }

        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = KeywordRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет переданные поля.
     *
     * @param int $keywordId Идентификатор.
     * @param array<string, mixed> $fields Колонки без code.
     *
     * @return void
     *
     * @throws KeywordNotFoundException Если строки нет.
     * @throws KeywordInvalidException Если поле.
     */
    public function update(int $keywordId, array $fields): void
    {
        try {
            $this->keywordRecords->update($keywordId, $fields);
        } catch (RowNotFoundException $exception) {
            throw new KeywordNotFoundException('Keyword was not found', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new KeywordInvalidException('Keyword field is invalid', $exception);
        }
    }
}
