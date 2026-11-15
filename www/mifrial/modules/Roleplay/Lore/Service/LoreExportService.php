<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreSectionRecord;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreSectionRepository;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Сборка файла `powerscale.lore` из мира кодекса.
 */
final class LoreExportService
{
    /**
     * Создаёт сборщик.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreSectionRepository $sectionRepository Строки разделов.
     * @param LoreArticleRepository $articleRepository Строки статей.
     * @param IKeywords $keywords Признаки.
     * @param IUserAccounts $userAccounts Учётки.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreSectionRepository $sectionRepository,
        private readonly LoreArticleRepository $articleRepository,
        private readonly IKeywords $keywords,
        private readonly IUserAccounts $userAccounts,
    ) {
    }

    /**
     * Собирает документ мира.
     *
     * @param RequestActor $actor Актор.
     * @param string $worldCode Код мира.
     *
     * @return array<string, mixed> Документ `powerscale.lore`.
     */
    public function export(RequestActor $actor, string $worldCode): array
    {
        $worldRecord = $this->accessService->requireWorldForEdit($actor, $worldCode);
        $sections = $this->sectionRepository->listByWorld($worldRecord->getId());
        $articles = $this->articleRepository->listByWorld($worldRecord->getId());
        usort(
            $articles,
            static fn (LoreArticleRecord $left, LoreArticleRecord $right): int => [
                $left->getSectionCode() ?? '',
                $left->getSortOrder(),
                $left->getCode(),
            ] <=> [
                $right->getSectionCode() ?? '',
                $right->getSortOrder(),
                $right->getCode(),
            ],
        );
        $keywordMap = $this->keywordMap($articles);
        $loginMap = $this->loginMap($articles);
        $ownerLogin = $this->userAccounts->getById($worldRecord->getOwnerId())->getLogin();

        return [
            'format' => 'powerscale.lore',
            'formatVersion' => 1,
            'exportedAt' => DateTime::now()->toUnix(),
            'world' => [
                'code' => $worldRecord->getCode(),
                'name' => $worldRecord->getName(),
                'description' => $worldRecord->getDescription(),
                'visibility' => $worldRecord->getVisibility(),
                'spaceCode' => $worldRecord->getSpaceCode(),
            ],
            'source' => [
                'ownerLogin' => $ownerLogin,
                'articleCount' => count($articles),
                'sectionCount' => count($sections),
            ],
            'sections' => $this->sectionRows($sections),
            'keywords' => $this->keywordRows($keywordMap),
            'articles' => $this->articleRows($articles, $keywordMap, $loginMap),
        ];
    }

    /**
     * Строки разделов.
     *
     * @param array<int, LoreSectionRecord> $sections Разделы.
     *
     * @return array<int, array<string, mixed>> Строки файла.
     */
    private function sectionRows(array $sections): array
    {
        $rows = [];
        foreach ($sections as $sectionRecord) {
            $rows[] = [
                'code' => $sectionRecord->getCode(),
                'name' => $sectionRecord->getName(),
                'description' => $sectionRecord->getDescription(),
                'parentCode' => $sectionRecord->getParentCode(),
                'sortOrder' => $sectionRecord->getSortOrder(),
                'icon' => $sectionRecord->getIcon(),
            ];
        }

        return $rows;
    }

    /**
     * Строки признаков.
     *
     * @param array<string, KeywordRecord> $keywordMap Код => признак.
     *
     * @return array<int, array<string, mixed>> Строки файла.
     */
    private function keywordRows(array $keywordMap): array
    {
        ksort($keywordMap);
        $rows = [];
        foreach ($keywordMap as $keywordRecord) {
            $rows[] = [
                'code' => $keywordRecord->getCode(),
                'name' => $keywordRecord->getName(),
                'description' => $keywordRecord->getDescription(),
                'active' => $keywordRecord->isActive(),
            ];
        }

        return $rows;
    }

    /**
     * Строки статей.
     *
     * @param array<int, LoreArticleRecord> $articles Статьи.
     * @param array<string, KeywordRecord> $keywordMap Код => признак.
     * @param array<int, string> $loginMap Id => логин.
     *
     * @return array<int, array<string, mixed>> Строки файла.
     */
    private function articleRows(array $articles, array $keywordMap, array $loginMap): array
    {
        $rows = [];
        foreach ($articles as $articleRecord) {
            $rows[] = $this->articleRow($articleRecord, $keywordMap, $loginMap);
        }

        return $rows;
    }

    /**
     * Снимки использованных признаков.
     *
     * @param array<int, LoreArticleRecord> $articles Статьи.
     *
     * @return array<string, KeywordRecord> Код => признак.
     */
    private function keywordMap(array $articles): array
    {
        $usedIds = [];
        foreach ($articles as $articleRecord) {
            foreach ($articleRecord->getKeywordIds() as $keywordId) {
                $usedIds[$keywordId] = true;
            }
        }

        if ($usedIds === []) {
            return [];
        }

        $map = [];
        foreach ($this->keywords->getList() as $keywordRecord) {
            if (isset($usedIds[$keywordRecord->getId()])) {
                $map[$keywordRecord->getCode()] = $keywordRecord;
            }
        }

        return $map;
    }

    /**
     * Логины читателей.
     *
     * @param array<int, LoreArticleRecord> $articles Статьи.
     *
     * @return array<int, string> Id => логин.
     */
    private function loginMap(array $articles): array
    {
        $userIds = [];
        foreach ($articles as $articleRecord) {
            foreach ($articleRecord->getReaderIds() as $readerId) {
                $userIds[$readerId] = true;
            }
        }

        if ($userIds === []) {
            return [];
        }

        $map = [];
        foreach ($this->userAccounts->getByIds(array_keys($userIds)) as $userRecord) {
            $map[$userRecord->getId()] = $userRecord->getLogin();
        }

        return $map;
    }

    /**
     * Строка статьи файла.
     *
     * @param LoreArticleRecord $articleRecord Статья.
     * @param array<string, KeywordRecord> $keywordMap Код => признак.
     * @param array<int, string> $loginMap Id => логин.
     *
     * @return array<string, mixed> Строка файла.
     */
    private function articleRow(
        LoreArticleRecord $articleRecord,
        array $keywordMap,
        array $loginMap,
    ): array {
        $keywordIds = array_flip($articleRecord->getKeywordIds());
        $keywordCodes = [];
        foreach ($keywordMap as $code => $keywordRecord) {
            if (isset($keywordIds[$keywordRecord->getId()])) {
                $keywordCodes[] = $code;
            }
        }

        $readers = [];
        foreach ($articleRecord->getReaderIds() as $readerId) {
            if (isset($loginMap[$readerId])) {
                $readers[] = $loginMap[$readerId];
            }
        }

        sort($readers);

        return [
            'code' => $articleRecord->getCode(),
            'title' => $articleRecord->getTitle(),
            'summary' => $articleRecord->getSummary(),
            'bodyHtml' => $articleRecord->getBodyHtml(),
            'sectionCode' => $articleRecord->getSectionCode(),
            'sortOrder' => $articleRecord->getSortOrder(),
            'keywordCodes' => $keywordCodes,
            'status' => $articleRecord->getStatus(),
            'visibility' => $articleRecord->getVisibility(),
            'readers' => $readers,
            'isArchived' => $articleRecord->isArchived(),
        ];
    }
}
