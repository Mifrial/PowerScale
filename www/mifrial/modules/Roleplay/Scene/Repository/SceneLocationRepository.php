<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Scene\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Scene\Dto\ScenePage;
use Mifrial\Roleplay\Scene\Dto\ScenePageQuery;
use Mifrial\Roleplay\Scene\Dto\SceneRecord;
use Mifrial\Roleplay\Scene\Exception\SceneInvalidException;
use Mifrial\Roleplay\Scene\Exception\SceneNotFoundException;
use Mifrial\Roleplay\Scene\Service\SceneRowErrors;

/**
 * Строки `scene_location`: каталог, доступ и версия.
 */
final class SceneLocationRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $locationRecords Строки локаций.
     * @param SceneRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $locationRecords,
        private readonly SceneRowErrors $errors,
    ) {
    }

    /**
     * Добавляет локацию.
     *
     * @param int $ownerId Владелец.
     * @param string $name Имя.
     * @param string $description Описание.
     * @param int $resolution Единиц сцены на ипари.
     * @param int $schemaVersion Версия формы снимка.
     *
     * @return int Id.
     *
     * @throws SceneInvalidException Если поля или строка недопустимы.
     */
    public function add(int $ownerId, string $name, string $description, int $resolution, int $schemaVersion): int
    {
        return $this->errors->insert(
            $this->locationRecords,
            [
                'owner_id' => $ownerId,
                'name' => $name,
                'description' => $description,
                'resolution' => $resolution,
                'schema_version' => $schemaVersion,
                'is_archived' => false,
                'version' => 1,
            ],
            'Scene row is invalid',
        );
    }

    /**
     * Локация по id.
     *
     * @param int $sceneId Id.
     *
     * @return SceneRecord Локация.
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если строка битая.
     */
    public function getById(int $sceneId): SceneRecord
    {
        $row = $this->locationRecords->getById($sceneId);
        if ($row === null) {
            throw new SceneNotFoundException();
        }

        return SceneRecord::fromNormalized($row);
    }

    /**
     * Страница видимых локаций.
     *
     * @param ScenePageQuery $pageQuery Запрос.
     *
     * @return ScenePage Страница.
     *
     * @throws SceneInvalidException Если фильтр или выборка недопустимы.
     */
    public function findPage(ScenePageQuery $pageQuery): ScenePage
    {
        $listResult = $this->errors->list(
            $this->locationRecords,
            ListQuery::fromOptions([
                'filter' => $this->pageFilter($pageQuery),
                'sort' => ['updated_at' => 'desc', 'id' => 'desc'],
                'limit' => $pageQuery->getLimit(),
                'offset' => $pageQuery->getOffset(),
                'countTotal' => true,
            ]),
            'Scene page query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = SceneRecord::fromNormalized($row);
        }

        return new ScenePage($records, $listResult->total() ?? 0);
    }

    /**
     * Все локации, включая архивные.
     *
     * @return array<int, SceneRecord> Локации.
     *
     * @throws SceneInvalidException Если выборка недопустима.
     */
    public function findAll(): array
    {
        $listResult = $this->errors->list(
            $this->locationRecords,
            ListQuery::fromOptions([
                'sort' => ['id' => 'asc'],
                'limit' => ListQuery::MAX_LIMIT,
                'offset' => 0,
                'countTotal' => false,
            ]),
            'Scene list query is invalid',
        );
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = SceneRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Пишет поля локации.
     *
     * @param int $sceneId Id.
     * @param array<string, mixed> $fields Поля.
     *
     * @return void
     *
     * @throws SceneNotFoundException Если строки нет.
     * @throws SceneInvalidException Если поле недопустимо.
     */
    public function update(int $sceneId, array $fields): void
    {
        $this->errors->updateById($this->locationRecords, $sceneId, $fields, 'Scene field is invalid');
    }

    /**
     * Фильтр страницы локаций.
     *
     * @param ScenePageQuery $pageQuery Запрос.
     *
     * @return array<int|string, mixed> Фильтр.
     */
    private function pageFilter(ScenePageQuery $pageQuery): array
    {
        $filter = ['LOGIC' => 'AND', ['=is_archived' => $pageQuery->isArchived()]];
        if (!$pageQuery->canViewAll()) {
            $filter[] = ['=owner_id' => $pageQuery->getViewerId()];
        }

        $searchQuery = $pageQuery->getSearchQuery();
        if ($searchQuery !== null) {
            $filter[] = ['%name' => '%' . $searchQuery . '%'];
        }

        return $filter;
    }
}
