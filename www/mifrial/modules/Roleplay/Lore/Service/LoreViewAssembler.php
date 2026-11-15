<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

use Mifrial\Roleplay\Lore\Dto\LoreArticleDetail;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreArticleSummary;
use Mifrial\Roleplay\Lore\Dto\LoreSectionRecord;
use Mifrial\Roleplay\Lore\Dto\LoreWorldDetail;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;

/**
 * JSON-представление миров, разделов и статей кодекса.
 */
final class LoreViewAssembler
{
    /**
     * Мир для списка или шапки.
     *
     * @param LoreWorldRecord $worldRecord Мир.
     * @param bool $canEdit Правка доступна.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleWorld(LoreWorldRecord $worldRecord, bool $canEdit = false): array
    {
        return [
            'id' => $worldRecord->getId(),
            'ownerId' => $worldRecord->getOwnerId(),
            'code' => $worldRecord->getCode(),
            'name' => $worldRecord->getName(),
            'description' => $worldRecord->getDescription(),
            'visibility' => $worldRecord->getVisibility(),
            'spaceCode' => $worldRecord->getSpaceCode(),
            'version' => $worldRecord->getVersion(),
            'isArchived' => $worldRecord->isArchived(),
            'canEdit' => $canEdit,
            'updatedBy' => $worldRecord->getUpdatedBy(),
            'createdAt' => $worldRecord->getCreatedAt()->toUnix(),
            'updatedAt' => $worldRecord->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Мир с деревом и срезами статей.
     *
     * @param LoreWorldDetail $detail Снимок.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleWorldDetail(LoreWorldDetail $detail): array
    {
        $sections = [];
        foreach ($detail->getSections() as $sectionRecord) {
            $sections[] = $this->assembleSection($sectionRecord);
        }

        $articles = [];
        foreach ($detail->getArticles() as $articleSummary) {
            $articles[] = $this->assembleArticleSummary($articleSummary);
        }

        return $this->assembleWorld($detail->getWorld(), $detail->canEdit()) + [
            'sections' => $sections,
            'articles' => $articles,
            'sectionCount' => count($sections),
            'articleCount' => count($articles),
        ];
    }

    /**
     * Раздел дерева.
     *
     * @param LoreSectionRecord $sectionRecord Раздел.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleSection(LoreSectionRecord $sectionRecord): array
    {
        return [
            'id' => $sectionRecord->getId(),
            'code' => $sectionRecord->getCode(),
            'name' => $sectionRecord->getName(),
            'description' => $sectionRecord->getDescription(),
            'parentCode' => $sectionRecord->getParentCode(),
            'sortOrder' => $sectionRecord->getSortOrder(),
            'icon' => $sectionRecord->getIcon(),
        ];
    }

    /**
     * Статья без тела.
     *
     * @param LoreArticleSummary $articleSummary Срез.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleArticleSummary(LoreArticleSummary $articleSummary): array
    {
        return [
            'id' => $articleSummary->getId(),
            'code' => $articleSummary->getCode(),
            'title' => $articleSummary->getTitle(),
            'summary' => $articleSummary->getSummary(),
            'sectionCode' => $articleSummary->getSectionCode(),
            'sortOrder' => $articleSummary->getSortOrder(),
            'keywordIds' => $articleSummary->getKeywordIds(),
            'status' => $articleSummary->getStatus(),
            'visibility' => $articleSummary->getVisibility(),
            'isArchived' => $articleSummary->isArchived(),
            'version' => $articleSummary->getVersion(),
            'updatedAt' => $articleSummary->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Полная статья.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleArticle(LoreArticleRecord $articleRecord): array
    {
        return [
            'id' => $articleRecord->getId(),
            'code' => $articleRecord->getCode(),
            'title' => $articleRecord->getTitle(),
            'summary' => $articleRecord->getSummary(),
            'bodyHtml' => $articleRecord->getBodyHtml(),
            'sectionCode' => $articleRecord->getSectionCode(),
            'sortOrder' => $articleRecord->getSortOrder(),
            'keywordIds' => $articleRecord->getKeywordIds(),
            'status' => $articleRecord->getStatus(),
            'visibility' => $articleRecord->getVisibility(),
            'readerIds' => $articleRecord->getReaderIds(),
            'version' => $articleRecord->getVersion(),
            'isArchived' => $articleRecord->isArchived(),
            'createdBy' => $articleRecord->getCreatedBy(),
            'updatedBy' => $articleRecord->getUpdatedBy(),
            'createdAt' => $articleRecord->getCreatedAt()->toUnix(),
            'updatedAt' => $articleRecord->getUpdatedAt()->toUnix(),
        ];
    }

    /**
     * Статья с ссылками и читателями.
     *
     * @param LoreArticleDetail $detail Снимок.
     *
     * @return array<string, mixed> JSON.
     */
    public function assembleArticleDetail(LoreArticleDetail $detail): array
    {
        return $this->assembleArticle($detail->getArticle()) + [
            'canEdit' => $detail->canEdit(),
            'links' => $this->linkRows($detail),
            'backlinks' => $this->backlinkRows($detail),
            'readers' => $this->readerRows($detail),
        ];
    }

    /**
     * Исходящие ссылки.
     *
     * @param LoreArticleDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function linkRows(LoreArticleDetail $detail): array
    {
        $links = [];
        foreach ($detail->getLinks() as $link) {
            $links[] = ['type' => $link->getType(), 'code' => $link->getCode()];
        }

        return $links;
    }

    /**
     * Ссылки сюда.
     *
     * @param LoreArticleDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function backlinkRows(LoreArticleDetail $detail): array
    {
        $backlinks = [];
        foreach ($detail->getBacklinks() as $backlink) {
            $backlinks[] = [
                'articleId' => $backlink->getArticleId(),
                'code' => $backlink->getCode(),
                'title' => $backlink->getTitle(),
            ];
        }

        return $backlinks;
    }

    /**
     * Читатели secret.
     *
     * @param LoreArticleDetail $detail Снимок.
     *
     * @return array<int, array<string, mixed>> JSON.
     */
    private function readerRows(LoreArticleDetail $detail): array
    {
        $readers = [];
        foreach ($detail->getReaders() as $reader) {
            $readers[] = [
                'id' => $reader->getId(),
                'login' => $reader->getLogin(),
                'name' => $reader->getName(),
            ];
        }

        return $readers;
    }
}
