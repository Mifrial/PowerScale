<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Repository;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Lore\Dto\LoreWorldPage;
use Mifrial\Roleplay\Lore\Dto\LoreWorldQuery;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;
use Mifrial\Roleplay\Lore\Dto\LoreWorldValues;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;
use Mifrial\Roleplay\Lore\Service\LoreRowErrors;

/**
 * Строки `lore_world`: каталог, код, версия и архив.
 */
final class LoreWorldRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $worldRecords Строки миров.
     * @param LoreRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $worldRecords,
        private readonly LoreRowErrors $errors,
    ) {
    }

    /**
     * Добавляет мир.
     *
     * @param LoreWorldValues $values Поля.
     * @param int $ownerId Владелец.
     * @param DateTime $now Момент.
     *
     * @return LoreWorldRecord Мир.
     */
    public function add(LoreWorldValues $values, int $ownerId, DateTime $now): LoreWorldRecord
    {
        $worldId = $this->errors->insert($this->worldRecords, [
            'owner_id' => $ownerId,
            'code' => $values->getCode(),
            'name' => $values->getName(),
            'description' => $values->getDescription(),
            'visibility' => $values->getVisibility(),
            'space_code' => $values->getSpaceCode(),
            'version' => 1,
            'is_archived' => false,
            'updated_by' => $ownerId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'Lore world row is invalid');

        return $this->getById($worldId);
    }

    /**
     * Пишет мета мира и поднимает версию.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param LoreWorldValues $values Поля.
     * @param int $actorId Автор записи.
     * @param DateTime $now Момент.
     *
     * @return LoreWorldRecord Мир после записи.
     */
    public function update(
        LoreWorldRecord $worldRecord,
        LoreWorldValues $values,
        int $actorId,
        DateTime $now,
    ): LoreWorldRecord {
        $this->errors->updateById($this->worldRecords, $worldRecord->getId(), [
            'name' => $values->getName(),
            'description' => $values->getDescription(),
            'visibility' => $values->getVisibility(),
            'space_code' => $values->getSpaceCode(),
            'version' => $worldRecord->getVersion() + 1,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ], 'Lore world field is invalid');

        return $this->getById($worldRecord->getId());
    }

    /**
     * Ставит или снимает архив.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param bool $archived Архив.
     * @param int $actorId Автор записи.
     * @param DateTime $now Момент.
     *
     * @return LoreWorldRecord Мир после записи.
     */
    public function archive(
        LoreWorldRecord $worldRecord,
        bool $archived,
        int $actorId,
        DateTime $now,
    ): LoreWorldRecord {
        $this->errors->updateById($this->worldRecords, $worldRecord->getId(), [
            'is_archived' => $archived,
            'version' => $worldRecord->getVersion() + 1,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ], 'Lore world archive flag is invalid');

        return $this->getById($worldRecord->getId());
    }

    /**
     * Поднимает версию мира после правки дерева.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param int $actorId Автор записи.
     * @param DateTime $now Момент.
     *
     * @return LoreWorldRecord Мир после записи.
     */
    public function touch(LoreWorldRecord $worldRecord, int $actorId, DateTime $now): LoreWorldRecord
    {
        $this->errors->updateById($this->worldRecords, $worldRecord->getId(), [
            'version' => $worldRecord->getVersion() + 1,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ], 'Lore world version is invalid');

        return $this->getById($worldRecord->getId());
    }

    /**
     * Мир по id.
     *
     * @param int $worldId Id.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreNotFoundException Если строки нет.
     */
    public function getById(int $worldId): LoreWorldRecord
    {
        $row = $this->errors->getById($this->worldRecords, $worldId, 'Lore world row is invalid');
        if ($row === null) {
            throw new LoreNotFoundException('Lore world was not found');
        }

        return LoreWorldRecord::fromNormalized($row);
    }

    /**
     * Мир по коду.
     *
     * @param string $code Код.
     *
     * @return LoreWorldRecord Мир.
     *
     * @throws LoreNotFoundException Если строки нет.
     */
    public function getByCode(string $code): LoreWorldRecord
    {
        $worldRecord = $this->findByCode($code);
        if ($worldRecord === null) {
            throw new LoreNotFoundException('Lore world was not found');
        }

        return $worldRecord;
    }

    /**
     * Мир по коду или null.
     *
     * @param string $code Код.
     *
     * @return LoreWorldRecord|null Мир или null.
     */
    public function findByCode(string $code): ?LoreWorldRecord
    {
        $listResult = $this->errors->list($this->worldRecords, ListQuery::fromOptions([
            'filter' => ['=code' => $code],
            'sort' => ['id' => 'asc'],
            'limit' => 1,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore world lookup is invalid');
        $rows = $listResult->rows();
        $row = $rows[0] ?? null;

        return is_array($row) ? LoreWorldRecord::fromNormalized($row) : null;
    }

    /**
     * Страница видимых миров.
     *
     * @param LoreWorldQuery $query Запрос.
     *
     * @return LoreWorldPage Страница.
     */
    public function findPage(LoreWorldQuery $query): LoreWorldPage
    {
        $listResult = $this->errors->list($this->worldRecords, ListQuery::fromOptions([
            'filter' => $this->pageFilter($query),
            'sort' => ['updated_at' => 'desc', 'id' => 'desc'],
            'limit' => $query->getLimit(),
            'offset' => $query->getOffset(),
            'countTotal' => true,
        ]), 'Lore world page query is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreWorldRecord::fromNormalized($row);
        }

        return new LoreWorldPage($records, $listResult->total() ?? 0);
    }

    /**
     * Код уже занят другим миром.
     *
     * @param string $code Код.
     * @param int|null $exceptWorldId Мир, который не считаем.
     *
     * @return bool true, если занят.
     */
    public function isCodeTaken(string $code, ?int $exceptWorldId = null): bool
    {
        $worldRecord = $this->findByCode($code);

        return $worldRecord !== null && $worldRecord->getId() !== $exceptWorldId;
    }

    /**
     * Фильтр страницы миров.
     *
     * @param LoreWorldQuery $query Запрос.
     *
     * @return array<string|int, mixed> Фильтр.
     */
    private function pageFilter(LoreWorldQuery $query): array
    {
        $clauses = [];
        $stateClause = match ($query->getState()) {
            'active' => ['=is_archived' => false],
            'archived' => ['=is_archived' => true],
            default => null,
        };
        if ($stateClause !== null) {
            $clauses[] = $stateClause;
        }

        if (!$query->canViewAll()) {
            $clauses[] = [
                'LOGIC' => 'OR',
                ['=owner_id' => $query->getViewerId()],
                ['=visibility' => 'public'],
            ];
        }

        $visibility = $query->getVisibility();
        if ($visibility !== null) {
            $clauses[] = ['=visibility' => $visibility];
        }

        $searchQuery = $query->getSearchQuery();
        if ($searchQuery !== null) {
            $clauses[] = [
                'LOGIC' => 'OR',
                ['%name' => '%' . $searchQuery . '%'],
                ['%description' => '%' . $searchQuery . '%'],
                ['%code' => '%' . $searchQuery . '%'],
            ];
        }

        if ($clauses === []) {
            return [];
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        return ['LOGIC' => 'AND', ...$clauses];
    }


}
