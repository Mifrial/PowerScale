<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Repository;

use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;
use Mifrial\Roleplay\Lore\Dto\LoreLinkValues;
use Mifrial\Roleplay\Lore\Dto\LoreValues;
use Mifrial\Roleplay\Lore\Service\LoreRowErrors;

/**
 * Строки `lore_link`: исходящие ссылки статей и обратный поиск.
 */
final class LoreLinkRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $linkRecords Строки ссылок.
     * @param LoreRowErrors $errors Карта ошибок строк.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $linkRecords,
        private readonly LoreRowErrors $errors,
    ) {
    }

    /**
     * Пересобирает ссылки статьи.
     *
     * @param int $worldId Мир.
     * @param int $sourceArticleId Статья.
     * @param array<int, LoreLinkValues> $links Ссылки.
     *
     * @return void
     */
    public function replaceForArticle(int $worldId, int $sourceArticleId, array $links): void
    {
        $this->clearForArticle($sourceArticleId);

        $rows = [];
        foreach ($links as $linkValues) {
            $rows[] = [
                'world_id' => $worldId,
                'source_article_id' => $sourceArticleId,
                'target_type' => $linkValues->getType(),
                'target_code' => $linkValues->getCode(),
            ];
        }

        $this->errors->insertMany($this->linkRecords, $rows, 'Lore link row is invalid');
    }

    /**
     * Удаляет ссылки статьи.
     *
     * @param int $sourceArticleId Статья.
     *
     * @return void
     */
    public function clearForArticle(int $sourceArticleId): void
    {
        $listResult = $this->errors->list($this->linkRecords, ListQuery::fromOptions([
            'filter' => ['=source_article_id' => $sourceArticleId],
            'sort' => ['id' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore link list is invalid');
        foreach ($listResult->rows() as $row) {
            $linkId = LoreValues::requireInt($row, 'id');
            $this->errors->deleteById($this->linkRecords, $linkId, 'Lore link cannot be removed');
        }
    }

    /**
     * Исходящие ссылки статьи.
     *
     * @param int $sourceArticleId Статья.
     *
     * @return array<int, LoreLinkValues> Ссылки.
     */
    public function listOutgoing(int $sourceArticleId): array
    {
        $listResult = $this->errors->list($this->linkRecords, ListQuery::fromOptions([
            'filter' => ['=source_article_id' => $sourceArticleId],
            'sort' => ['target_type' => 'asc', 'target_code' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore link list is invalid');
        $links = [];
        foreach ($listResult->rows() as $row) {
            $links[] = new LoreLinkValues(
                LoreValues::requireString($row, 'target_type'),
                LoreValues::requireString($row, 'target_code'),
            );
        }

        return $links;
    }

    /**
     * Статьи мира, которые ссылаются на цель.
     *
     * @param int $worldId Мир.
     * @param string $targetType Тип цели.
     * @param string $targetCode Код цели.
     *
     * @return array<int, int> Id статей-источников.
     */
    public function sourceIdsByTarget(int $worldId, string $targetType, string $targetCode): array
    {
        $listResult = $this->errors->list($this->linkRecords, ListQuery::fromOptions([
            'filter' => [
                '=world_id' => $worldId,
                '=target_type' => $targetType,
                '=target_code' => $targetCode,
            ],
            'sort' => ['source_article_id' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore backlink query is invalid');
        $sourceIds = [];
        foreach ($listResult->rows() as $row) {
            $sourceIds[] = LoreValues::requireInt($row, 'source_article_id');
        }

        return array_values(array_unique($sourceIds));
    }

    /**
     * Коды статей мира, на которые есть ссылки.
     *
     * @param int $worldId Мир.
     *
     * @return array<int, string> Коды целей типа lore.
     */
    public function targetCodes(int $worldId): array
    {
        $listResult = $this->errors->list($this->linkRecords, ListQuery::fromOptions([
            'filter' => ['=world_id' => $worldId, '=target_type' => 'lore'],
            'sort' => ['target_code' => 'asc'],
            'limit' => 10000,
            'offset' => 0,
            'countTotal' => false,
        ]), 'Lore link target query is invalid');
        $codes = [];
        foreach ($listResult->rows() as $row) {
            $codes[] = LoreValues::requireString($row, 'target_code');
        }

        return array_values(array_unique($codes));
    }
}
