<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\RuleSpace\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\ReferenceConstraintException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;

/**
 * Строки sidecar `rulespace`.
 */
final class RuleSpaceRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $ruleSpaceRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $ruleSpaceRecords,
    ) {
    }

    /**
     * Пишет мета мира.
     *
     * @param int $spaceId Часы.
     * @param string $code Уже trim.
     * @param string $name Уже trim.
     * @param int $ownerUserId Владелец.
     * @param string $description Текст.
     *
     * @return void
     *
     * @throws RuleSpaceInvalidException Если unique, FK или поле.
     */
    public function add(int $spaceId, string $code, string $name, int $ownerUserId, string $description): void
    {
        try {
            $this->ruleSpaceRecords->add([
                'space_id' => $spaceId,
                'code' => $code,
                'name' => $name,
                'owner_id' => $ownerUserId,
                'description' => $description,
            ]);
        } catch (UniqueConstraintException $exception) {
            throw new RuleSpaceInvalidException('Rule space unique is violated', $exception);
        } catch (ReferenceConstraintException $exception) {
            throw new RuleSpaceInvalidException('Rule space owner is invalid', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new RuleSpaceInvalidException('Rule space field is invalid', $exception);
        }
    }

    /**
     * Строка по space_id.
     *
     * @param int $spaceId Часы.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceNotFoundException Если строки нет.
     * @throws RuleSpaceInvalidException Если Record неполный.
     */
    public function getBySpaceId(int $spaceId): RuleSpaceRecord
    {
        $row = $this->ruleSpaceRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['space_id' => $spaceId],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new RuleSpaceNotFoundException();
        }

        return RuleSpaceRecord::fromNormalized($row);
    }

    /**
     * Пишет поля sidecar по space_id.
     *
     * @param int $spaceId Часы.
     * @param array<string, mixed> $fields Непустые колонки.
     *
     * @return void
     *
     * @throws RuleSpaceNotFoundException Если строки нет.
     * @throws RuleSpaceInvalidException Если поле.
     */
    public function updateBySpaceId(int $spaceId, array $fields): void
    {
        $row = $this->ruleSpaceRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['space_id' => $spaceId],
            'limit' => 1,
        ]));
        if ($row === null || !is_int($row['id'] ?? null)) {
            throw new RuleSpaceNotFoundException();
        }

        try {
            $this->ruleSpaceRecords->update($row['id'], $fields);
        } catch (RowNotFoundException $exception) {
            throw new RuleSpaceNotFoundException('Rule space was not found', $exception);
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new RuleSpaceInvalidException('Rule space field is invalid', $exception);
        }
    }

    /**
     * Строка по code.
     *
     * @param string $code Уже trim.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceNotFoundException Если строки нет.
     * @throws RuleSpaceInvalidException Если Record неполный.
     */
    public function getByCode(string $code): RuleSpaceRecord
    {
        $row = $this->ruleSpaceRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['code' => $code],
            'limit' => 1,
        ]));
        if ($row === null) {
            throw new RuleSpaceNotFoundException();
        }

        return RuleSpaceRecord::fromNormalized($row);
    }

    /**
     * Живые миры, до limit.
     *
     * @param int $limit Потолок страницы.
     *
     * @return array<int, RuleSpaceRecord> Миры.
     *
     * @throws RuleSpaceInvalidException Если выборка битая.
     */
    public function getActiveList(int $limit): array
    {
        try {
            $listResult = $this->ruleSpaceRecords->getList(ListQuery::fromOptions([
                'filter' => ['active' => true],
                'sort' => ['id' => 'asc'],
                'limit' => $limit,
            ]));
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new RuleSpaceInvalidException('Rule space list is invalid', $exception);
        }

        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = RuleSpaceRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Живые миры владельца, до limit.
     *
     * @param int $ownerUserId Владелец.
     * @param int $limit Потолок страницы.
     *
     * @return array<int, RuleSpaceRecord> Миры.
     *
     * @throws RuleSpaceInvalidException Если выборка битая.
     */
    public function getActiveListByOwner(int $ownerUserId, int $limit): array
    {
        try {
            $listResult = $this->ruleSpaceRecords->getList(ListQuery::fromOptions([
                'filter' => ['active' => true, 'owner_id' => $ownerUserId],
                'sort' => ['id' => 'asc'],
                'limit' => $limit,
            ]));
        } catch (FieldRequiredException | FieldInvalidException | MapInvalidException $exception) {
            throw new RuleSpaceInvalidException('Rule space list is invalid', $exception);
        }

        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = RuleSpaceRecord::fromNormalized($row);
        }

        return $records;
    }
}
