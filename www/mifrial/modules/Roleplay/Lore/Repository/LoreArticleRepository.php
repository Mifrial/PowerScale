<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Repository;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong,MifrialCodingStandard.Metrics.MethodQuality.CognitiveComplexityTooHigh,MifrialCodingStandard.Metrics.MethodQuality.CyclomaticComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Lore\Dto\LoreArticlePage;
use Mifrial\Roleplay\Lore\Dto\LoreArticleQuery;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreArticleSummary;
use Mifrial\Roleplay\Lore\Dto\LoreArticleValues;
use Mifrial\Roleplay\Lore\Dto\LoreValues;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;
use Mifrial\Roleplay\Lore\Service\LoreRowErrors;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

/**
 * Строки `lore_article`: поиск, запись, версия и архив.
 */
final class LoreArticleRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $articleRecords Строки статей.
     * @param LoreRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $articleRecords,
        private readonly LoreRowErrors $errors,
    ) {
    }

    /**
     * Добавляет статью.
     *
     * @param int $worldId Мир.
     * @param LoreArticleValues $values Поля.
     * @param int $authorId Автор.
     * @param DateTime $now Момент.
     *
     * @return LoreArticleRecord Статья.
     */
    public function add(int $worldId, LoreArticleValues $values, int $authorId, DateTime $now): LoreArticleRecord
    {
        $articleId = $this->errors->insert($this->articleRecords, [
            'world_id' => $worldId,
            'code' => $values->getCode(),
            'title' => $values->getTitle(),
            'summary' => $values->getSummary(),
            'body_html' => $values->getBodyHtml(),
            'body_text' => $values->getBodyText(),
            'section_code' => $values->getSectionCode(),
            'sort_order' => $values->getSortOrder(),
            'keywords' => $values->getKeywordIds(),
            'status' => $values->getStatus(),
            'visibility' => $values->getVisibility(),
            'readers' => $values->getReaderIds(),
            'version' => 1,
            'is_archived' => false,
            'created_by' => $authorId,
            'updated_by' => $authorId,
            'created_at' => $now,
            'updated_at' => $now,
        ], 'Lore article row is invalid');

        return $this->getById($articleId);
    }

    /**
     * Пишет статью и поднимает версию.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     * @param LoreArticleValues $values Поля.
     * @param int $actorId Автор записи.
     * @param DateTime $now Момент.
     *
     * @return LoreArticleRecord Статья после записи.
     */
    public function update(
        LoreArticleRecord $articleRecord,
        LoreArticleValues $values,
        int $actorId,
        DateTime $now,
    ): LoreArticleRecord {
        $this->errors->updateById($this->articleRecords, $articleRecord->getId(), [
            'title' => $values->getTitle(),
            'summary' => $values->getSummary(),
            'body_html' => $values->getBodyHtml(),
            'body_text' => $values->getBodyText(),
            'section_code' => $values->getSectionCode(),
            'sort_order' => $values->getSortOrder(),
            'keywords' => $values->getKeywordIds(),
            'status' => $values->getStatus(),
            'visibility' => $values->getVisibility(),
            'readers' => $values->getReaderIds(),
            'version' => $articleRecord->getVersion() + 1,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ], 'Lore article field is invalid');

        return $this->getById($articleRecord->getId());
    }

    /**
     * Ставит или снимает архив.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     * @param bool $archived Архив.
     * @param int $actorId Автор записи.
     * @param DateTime $now Момент.
     *
     * @return LoreArticleRecord Статья после записи.
     */
    public function archive(
        LoreArticleRecord $articleRecord,
        bool $archived,
        int $actorId,
        DateTime $now,
    ): LoreArticleRecord {
        $this->errors->updateById($this->articleRecords, $articleRecord->getId(), [
            'is_archived' => $archived,
            'version' => $articleRecord->getVersion() + 1,
            'updated_by' => $actorId,
            'updated_at' => $now,
        ], 'Lore article archive flag is invalid');

        return $this->getById($articleRecord->getId());
    }

    /**
     * Статья по id.
     *
     * @param int $articleId Id.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreNotFoundException Если строки нет.
     */
    public function getById(int $articleId): LoreArticleRecord
    {
        $row = $this->errors->getById($this->articleRecords, $articleId, 'Lore article row is invalid');
        if ($row === null) {
            throw new LoreNotFoundException('Lore article was not found');
        }

        return LoreArticleRecord::fromNormalized($row);
    }

    /**
     * Статья мира по коду.
     *
     * @param int $worldId Мир.
     * @param string $articleCode Код.
     *
     * @return LoreArticleRecord Статья.
     *
     * @throws LoreNotFoundException Если строки нет.
     */
    public function getByCode(int $worldId, string $articleCode): LoreArticleRecord
    {
        $articleRecord = $this->findByCode($worldId, $articleCode);
        if ($articleRecord === null) {
            throw new LoreNotFoundException('Lore article was not found');
        }

        return $articleRecord;
    }

    /**
     * Статья мира по коду или null.
     *
     * @param int $worldId Мир.
     * @param string $articleCode Код.
     *
     * @return LoreArticleRecord|null Статья или null.
     */
    public function findByCode(int $worldId, string $articleCode): ?LoreArticleRecord
    {
        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['=world_id' => $worldId, '=code' => $articleCode],
            'sort' => ['id' => 'asc'],
            'limit' => 1,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore article lookup is invalid');
        $rows = $listResult->rows();
        $row = $rows[0] ?? null;

        return is_array($row) ? LoreArticleRecord::fromNormalized($row) : null;
    }

    /**
     * Страница доступных статей мира.
     *
     * @param LoreArticleQuery $query Запрос.
     *
     * @return LoreArticlePage Страница.
     */
    public function findPage(LoreArticleQuery $query): LoreArticlePage
    {
        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => $this->pageFilter($query),
            'sort' => $query->getSort() === 'recent'
                ? ['updated_at' => 'desc', 'id' => 'desc']
                : ['section_code' => 'asc', 'sort_order' => 'asc', 'title' => 'asc'],
            'limit' => $query->getLimit(),
            'offset' => $query->getOffset(),
            'countTotal' => true,
        ]), 'Lore article page query is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreArticleSummary::fromNormalized($row);
        }

        return new LoreArticlePage($records, $listResult->total() ?? 0);
    }

    /**
     * Срезы статей для дерева.
     *
     * @param int $worldId Мир.
     * @param bool $canViewAll Видит черновики и секреты.
     * @param int $viewerId Зритель.
     *
     * @return array<int, LoreArticleSummary> Статьи.
     */
    public function listForTree(int $worldId, bool $canViewAll, int $viewerId): array
    {
        $clauses = [['=world_id' => $worldId], ['=is_archived' => false]];
        $accessClause = $this->accessClause($canViewAll, $viewerId);
        if ($accessClause !== null) {
            $clauses[] = $accessClause;
        }

        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['LOGIC' => 'AND', ...$clauses],
            'sort' => ['section_code' => 'asc', 'sort_order' => 'asc', 'title' => 'asc'],
            'limit' => 5000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore article tree query is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreArticleSummary::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Срезы статей по списку id.
     *
     * @param array<int, int> $articleIds Id.
     *
     * @return array<int, LoreArticleSummary> Статьи в порядке id.
     */
    public function listByIds(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['=id' => $articleIds],
            'sort' => ['id' => 'asc'],
            'limit' => 5000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore article batch query is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreArticleSummary::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Поля доступа к статьям по списку id без тела.
     *
     * @param array<int, int> $articleIds Id.
     *
     * @return array<int, array{
     *     id: int,
     *     code: string,
     *     title: string,
     *     status: string,
     *     visibility: string,
     *     readerIds: array<int, int>
     * }> Строки доступа.
     */
    public function listAccessRows(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }

        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['=id' => $articleIds],
            'sort' => ['id' => 'asc'],
            'limit' => 5000,
            'offset' => 0,
            'countTotal' => false,
            'select' => ['id', 'code', 'title', 'status', 'visibility', 'readers'],
        ]), 'Lore article access query is invalid');
        $rows = [];
        foreach ($listResult->rows() as $row) {
            $rows[] = [
                'id' => LoreValues::requireInt($row, 'id'),
                'code' => LoreValues::requireString($row, 'code'),
                'title' => LoreValues::requireString($row, 'title'),
                'status' => LoreValues::requireString($row, 'status'),
                'visibility' => LoreValues::requireString($row, 'visibility'),
                'readerIds' => LoreValues::requireIntList($row, 'readers'),
            ];
        }

        return $rows;
    }

    /**
     * Коды статей мира.
     *
     * @param int $worldId Мир.
     *
     * @return array<int, string> Коды.
     */
    public function codesByWorld(int $worldId): array
    {
        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['=world_id' => $worldId],
            'sort' => ['id' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore article code query is invalid');
        $codes = [];
        foreach ($listResult->rows() as $row) {
            $articleRecord = LoreArticleRecord::fromNormalized($row);
            $codes[] = $articleRecord->getCode();
        }

        return $codes;
    }

    /**
     * Все статьи мира для экспорта и импорта.
     *
     * @param int $worldId Мир.
     *
     * @return array<int, LoreArticleRecord> Статьи.
     */
    public function listByWorld(int $worldId): array
    {
        $listResult = $this->errors->list($this->articleRecords, ListQuery::fromOptions([
            'filter' => ['=world_id' => $worldId],
            'sort' => ['section_code' => 'asc', 'sort_order' => 'asc', 'code' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore article export query is invalid');
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LoreArticleRecord::fromNormalized($row);
        }

        return $records;
    }

    /**
     * Фильтр страницы статей.
     *
     * @param LoreArticleQuery $query Запрос.
     *
     * @return array<string|int, mixed> Фильтр.
     */
    private function pageFilter(LoreArticleQuery $query): array
    {
        $clauses = [['=world_id' => $query->getWorldId()]];
        if (!$query->includeArchived()) {
            $clauses[] = ['=is_archived' => false];
        }

        $accessClause = $this->accessClause($query->canViewAll(), $query->getViewerId());
        if ($accessClause !== null) {
            $clauses[] = $accessClause;
        }

        $sectionCode = $query->getSectionCode();
        if ($sectionCode !== null) {
            $clauses[] = ['=section_code' => $sectionCode];
        }

        $keywordIds = $query->getKeywordIds();
        if ($keywordIds !== []) {
            $keywordClauses = [];
            foreach ($keywordIds as $keywordId) {
                $keywordClauses[] = ['@keywords' => $keywordId];
            }

            $clauses[] = ['LOGIC' => 'OR', ...$keywordClauses];
        }

        $status = $query->getStatus();
        if ($status !== null) {
            $clauses[] = ['=status' => $status];
        }

        $visibility = $query->getVisibility();
        if ($visibility !== null) {
            $clauses[] = ['=visibility' => $visibility];
        }

        $searchQuery = $query->getSearchQuery();
        if ($searchQuery !== null) {
            $clauses[] = [
                'LOGIC' => 'OR',
                ['%title' => '%' . $searchQuery . '%'],
                ['%summary' => '%' . $searchQuery . '%'],
                ['%body_text' => '%' . $searchQuery . '%'],
                ['%code' => '%' . $searchQuery . '%'],
            ];
        }

        return ['LOGIC' => 'AND', ...$clauses];
    }

    /**
     * Условие чтения для не-редактора.
     *
     * @param bool $canViewAll Видит черновики и секреты.
     * @param int $viewerId Зритель.
     *
     * @return array<string|int, mixed>|null Условие или null.
     */
    private function accessClause(bool $canViewAll, int $viewerId): ?array
    {
        if ($canViewAll) {
            return null;
        }

        return [
            'LOGIC' => 'AND',
            ['=status' => 'published'],
            [
                'LOGIC' => 'OR',
                ['=visibility' => 'public'],
                ['@readers' => $viewerId],
            ],
        ];
    }
}
