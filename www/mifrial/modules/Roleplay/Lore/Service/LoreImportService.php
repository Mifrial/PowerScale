<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.ClassTooLong,MifrialCodingStandard.Metrics.ClassQuality.ClassComplexityTooHigh,MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong,MifrialCodingStandard.Metrics.MethodQuality.CognitiveComplexityTooHigh,MifrialCodingStandard.Metrics.MethodQuality.CyclomaticComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Service\ITransactionRunner;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Lore\Dto\LoreArticleRecord;
use Mifrial\Roleplay\Lore\Dto\LoreArticleValues;
use Mifrial\Roleplay\Lore\Dto\LoreImportArticle;
use Mifrial\Roleplay\Lore\Dto\LoreImportDocument;
use Mifrial\Roleplay\Lore\Dto\LoreImportReport;
use Mifrial\Roleplay\Lore\Dto\LoreImportRequest;
use Mifrial\Roleplay\Lore\Dto\LoreImportWrite;
use Mifrial\Roleplay\Lore\Dto\LoreImportTarget;
use Mifrial\Roleplay\Lore\Dto\LoreSectionRecord;
use Mifrial\Roleplay\Lore\Dto\LoreSectionValues;
use Mifrial\Roleplay\Lore\Dto\LoreWorldRecord;
use Mifrial\Roleplay\Lore\Dto\LoreWorldValues;
use Mifrial\Roleplay\Lore\Exception\LoreDeniedException;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;
use Mifrial\Roleplay\Lore\Exception\LoreNotFoundException;
use Mifrial\Roleplay\Lore\Repository\LoreArticleRepository;
use Mifrial\Roleplay\Lore\Repository\LoreLinkRepository;
use Mifrial\Roleplay\Lore\Repository\LoreSectionRepository;
use Mifrial\Roleplay\Lore\Repository\LoreWorldRepository;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods

/**
 * Импорт файла `powerscale.lore` в новый или существующий мир.
 */
final class LoreImportService
{
    private const MAX_CODE_SUFFIX = 99;

    /**
     * Создаёт импорт.
     *
     * @param LoreAccessService $accessService Доступ.
     * @param LoreWorldRepository $worldRepository Строки миров.
     * @param LoreSectionRepository $sectionRepository Строки разделов.
     * @param LoreArticleRepository $articleRepository Строки статей.
     * @param LoreLinkRepository $linkRepository Строки ссылок.
     * @param IKeywords $keywords Признаки.
     * @param IUserAccounts $userAccounts Учётки.
     * @param LoreImportValidator $validator Проверка файла.
     * @param LoreTreeValidator $treeValidator Проверка дерева.
     * @param LoreLinkExtractor $linkExtractor Извлечение ссылок.
     * @param ITransactionRunner $transactionRunner Единица работы.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreAccessService $accessService,
        private readonly LoreWorldRepository $worldRepository,
        private readonly LoreSectionRepository $sectionRepository,
        private readonly LoreArticleRepository $articleRepository,
        private readonly LoreLinkRepository $linkRepository,
        private readonly IKeywords $keywords,
        private readonly IUserAccounts $userAccounts,
        private readonly LoreImportValidator $validator,
        private readonly LoreTreeValidator $treeValidator,
        private readonly LoreLinkExtractor $linkExtractor,
        private readonly ITransactionRunner $transactionRunner,
    ) {
    }

    /**
     * Выполняет импорт или сухой прогон.
     *
     * @param RequestActor $actor Актор.
     * @param LoreImportRequest $request Запрос.
     *
     * @return LoreImportReport Отчёт.
     *
     * @throws LoreInvalidException Если файл или режим недопустимы.
     */
    public function import(RequestActor $actor, LoreImportRequest $request): LoreImportReport
    {
        $this->assertMode($request);
        $document = $this->validator->validate($request->getPayload());
        $warnings = $document->getWarnings();
        $target = $this->resolveWorld($actor, $request, $document, $warnings);
        $sectionPlan = $this->sectionPlan($document, $target, $request);
        $keywordIds = $this->keywordIds($document, $request->isDryRun());
        $articlePlan = $this->articlePlan($document, $target, $request, $keywordIds, $warnings);

        $report = new LoreImportReport(
            $target->getCode(),
            $request->isDryRun(),
            ['sections' => $sectionPlan['created'], 'articles' => $articlePlan['created']],
            ['sections' => $sectionPlan['updated'], 'articles' => $articlePlan['updated']],
            ['sections' => $sectionPlan['skipped'], 'articles' => $articlePlan['skipped']],
            $warnings,
        );

        if ($request->isDryRun()) {
            return $report;
        }

        $this->transactionRunner->run(function () use (
            $actor,
            $target,
            $sectionPlan,
            $articlePlan,
        ): void {
            $now = DateTime::now();
            $worldRecord = $target->getWorldRecord();
            if ($worldRecord === null) {
                $worldRecord = $this->worldRepository->add(
                    $target->getWorldValues(),
                    $actor->getUserId(),
                    $now,
                );
            }

            $this->sectionRepository->replaceTree($worldRecord->getId(), $sectionPlan['sections'], $now);
            if (!$target->isNew()) {
                $this->worldRepository->touch($worldRecord, $actor->getUserId(), $now);
            }

            $this->writeArticles($actor, $worldRecord, $articlePlan['writes'], $now);
        });

        return $report;
    }

    /**
     * Проверяет режим и политику.
     *
     * @param LoreImportRequest $request Запрос.
     *
     * @return void
     *
     * @throws LoreInvalidException Если режим или политика конфликтов недопустимы.
     */
    private function assertMode(LoreImportRequest $request): void
    {
        if (!in_array($request->getMode(), ['new', 'merge'], true)) {
            throw new LoreInvalidException('Lore import mode must be new or merge');
        }

        if (!in_array($request->getConflictPolicy(), ['skip', 'overwrite'], true)) {
            throw new LoreInvalidException('Lore import conflict policy must be skip or overwrite');
        }

        if ($request->getMode() === 'merge' && $request->getTargetWorldCode() === null) {
            throw new LoreInvalidException('Lore import merge requires targetWorldCode');
        }
    }

    /**
     * Мир импорта: новый код или целевой мир.
     *
     * @param RequestActor $actor Актор.
     * @param LoreImportRequest $request Запрос.
     * @param LoreImportDocument $document Документ.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return LoreImportTarget Цель.
     *
     * @throws LoreInvalidException Если суффиксы кода мира кончились.
     * @throws LoreNotFoundException Если целевой мир не найден.
     * @throws LoreDeniedException Если правки целевого мира нет.
     */
    private function resolveWorld(
        RequestActor $actor,
        LoreImportRequest $request,
        LoreImportDocument $document,
        array &$warnings,
    ): LoreImportTarget {
        if ($request->getMode() === 'merge') {
            $targetCode = $request->getTargetWorldCode();
            if ($targetCode === null) {
                throw new LoreInvalidException('Lore import merge requires targetWorldCode');
            }

            $worldRecord = $this->accessService->requireWorldForEdit($actor, $targetCode);

            return new LoreImportTarget($document->getWorldValues(), $worldRecord);
        }

        $this->accessService->requireCreate();
        $values = $document->getWorldValues();
        $code = $this->availableWorldCode($values->getCode());
        if ($code !== $values->getCode()) {
            $warnings[] = [
                'code' => 'world-code-suffixed',
                'path' => 'world.code',
                'message' => 'World code ' . $values->getCode() . ' is taken, import uses ' . $code,
            ];
        }

        return new LoreImportTarget(new LoreWorldValues(
            $code,
            $values->getName(),
            $values->getDescription(),
            $values->getVisibility(),
            $values->getSpaceCode(),
        ), null);
    }

    /**
     * План разделов: слияние с существующими и проверка дерева.
     *
     * @param LoreImportDocument $document Документ.
     * @param LoreImportTarget $target Цель.
     * @param LoreImportRequest $request Запрос.
     *
     * @return array{created: int, updated: int, skipped: int, sections: array<int, LoreSectionValues>} План.
     */
    private function sectionPlan(
        LoreImportDocument $document,
        LoreImportTarget $target,
        LoreImportRequest $request,
    ): array {
        $existing = [];
        $worldRecord = $target->getWorldRecord();
        if ($worldRecord !== null) {
            foreach ($this->sectionRepository->listByWorld($worldRecord->getId()) as $sectionRecord) {
                $existing[$sectionRecord->getCode()] = $sectionRecord;
            }
        }

        $rows = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        foreach ($document->getSections() as $sectionValue) {
            $existingRecord = $existing[$sectionValue->getCode()] ?? null;
            if ($existingRecord === null) {
                $created++;
                $rows[] = $this->sectionRow($sectionValue);
                continue;
            }

            if ($request->getConflictPolicy() === 'overwrite') {
                $updated++;
                $rows[] = $this->sectionRow($sectionValue);
            } else {
                $skipped++;
                $rows[] = $this->sectionRowFromRecord($existingRecord);
            }
        }

        foreach ($existing as $code => $existingRecord) {
            if ($this->hasSection($document, $code)) {
                continue;
            }

            $rows[] = $this->sectionRowFromRecord($existingRecord);
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'sections' => $this->treeValidator->validate($rows),
        ];
    }

    /**
     * План статей: создание, замена, пропуск.
     *
     * @param LoreImportDocument $document Документ.
     * @param LoreImportTarget $target Цель.
     * @param LoreImportRequest $request Запрос.
     * @param array<string, int> $keywordIds Код признака => id.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return array{
     *     created: int,
     *     updated: int,
     *     skipped: int,
     *     writes: array<int, LoreImportWrite>
     * } План.
     */
    private function articlePlan(
        LoreImportDocument $document,
        LoreImportTarget $target,
        LoreImportRequest $request,
        array $keywordIds,
        array &$warnings,
    ): array {
        $worldRecord = $target->getWorldRecord();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $writes = [];
        foreach ($document->getArticles() as $importArticle) {
            $existingRecord = $worldRecord === null
                ? null
                : $this->articleRepository->findByCode(
                    $worldRecord->getId(),
                    $importArticle->getValues()->getCode(),
                );
            if ($existingRecord !== null && $request->getConflictPolicy() === 'skip') {
                $skipped++;
                continue;
            }

            $writes[] = new LoreImportWrite(
                $this->articleValues($importArticle, $keywordIds, $warnings),
                $existingRecord,
                $importArticle->isArchived(),
            );
            if ($existingRecord === null) {
                $created++;
            } else {
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'writes' => $writes];
    }

    /**
     * Поля статьи с разрешёнными признаками и читателями.
     *
     * @param LoreImportArticle $importArticle Статья файла.
     * @param array<string, int> $keywordIds Код признака => id.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return LoreArticleValues Поля.
     */
    private function articleValues(
        LoreImportArticle $importArticle,
        array $keywordIds,
        array &$warnings,
    ): LoreArticleValues {
        $source = $importArticle->getValues();
        $mappedKeywordIds = [];
        foreach ($importArticle->getKeywordCodes() as $keywordCode) {
            if (isset($keywordIds[$keywordCode])) {
                $mappedKeywordIds[] = $keywordIds[$keywordCode];
            }
        }

        return new LoreArticleValues(
            $source->getCode(),
            $source->getTitle(),
            $source->getSummary(),
            $source->getBodyHtml(),
            $source->getBodyText(),
            $source->getSectionCode(),
            $source->getSortOrder(),
            $mappedKeywordIds,
            $source->getStatus(),
            $source->getVisibility(),
            $this->readerIds($importArticle, $warnings),
        );
    }

    /**
     * Логины файла в id учёток.
     *
     * @param LoreImportArticle $importArticle Статья файла.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return array<int, int> Id читателей.
     */
    private function readerIds(LoreImportArticle $importArticle, array &$warnings): array
    {
        $readerIds = [];
        foreach ($importArticle->getReaderLogins() as $login) {
            $userRecord = $this->userAccounts->findByLogin($login);
            if ($userRecord === null) {
                $warnings[] = [
                    'code' => 'reader-unknown',
                    'path' => 'articles[' . $importArticle->getValues()->getCode() . '].readers',
                    'message' => 'Reader login ' . $login . ' was not found and is skipped',
                ];
                continue;
            }

            $readerIds[] = $userRecord->getId();
        }

        return $readerIds;
    }

    /**
     * Запись статей и ссылок.
     *
     * @param RequestActor $actor Актор.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param array<int, LoreImportWrite> $writes План.
     * @param DateTime $now Момент.
     *
     * @return void
     */
    private function writeArticles(
        RequestActor $actor,
        LoreWorldRecord $worldRecord,
        array $writes,
        DateTime $now,
    ): void {
        $bodies = [];
        foreach ($writes as $write) {
            $articleRecord = $this->writeArticle($write, $worldRecord, $actor, $now);
            $bodies[$articleRecord->getId()] = $write->getValues()->getBodyHtml();
        }

        foreach ($bodies as $articleId => $bodyHtml) {
            $this->linkRepository->replaceForArticle(
                $worldRecord->getId(),
                $articleId,
                $this->linkExtractor->extract($bodyHtml),
            );
        }
    }

    /**
     * Пишет одну статью и её архивный флаг.
     *
     * @param LoreImportWrite $write Запись плана.
     * @param LoreWorldRecord $worldRecord Мир.
     * @param RequestActor $actor Актор.
     * @param DateTime $now Момент.
     *
     * @return LoreArticleRecord Статья после записи.
     */
    private function writeArticle(
        LoreImportWrite $write,
        LoreWorldRecord $worldRecord,
        RequestActor $actor,
        DateTime $now,
    ): LoreArticleRecord {
        $existing = $write->getExisting();
        $articleRecord = $existing === null
            ? $this->articleRepository->add($worldRecord->getId(), $write->getValues(), $actor->getUserId(), $now)
            : $this->articleRepository->update($existing, $write->getValues(), $actor->getUserId(), $now);
        if ($articleRecord->isArchived() === $write->isArchived()) {
            return $articleRecord;
        }

        return $this->articleRepository->archive(
            $articleRecord,
            $write->isArchived(),
            $actor->getUserId(),
            $now,
        );
    }

    /**
     * Признаки файла в id справочника, при необходимости досоздаёт.
     *
     * @param LoreImportDocument $document Документ.
     * @param bool $dryRun Не создавать признаки.
     *
     * @return array<string, int> Код => id.
     */
    private function keywordIds(LoreImportDocument $document, bool $dryRun): array
    {
        $map = [];
        foreach ($document->getKeywords() as $keyword) {
            if (array_key_exists($keyword->getCode(), $map)) {
                continue;
            }

            try {
                $record = $this->keywords->getByCode($keyword->getCode());
                $map[$keyword->getCode()] = $record->getId();
            } catch (KeywordNotFoundException) {
                if ($dryRun) {
                    continue;
                }

                $map[$keyword->getCode()] = $this->keywords->add(
                    $keyword->getCode(),
                    $keyword->getName(),
                    $keyword->getDescription(),
                    $keyword->isActive(),
                );
            }
        }

        return $map;
    }

    /**
     * Свободный код мира с суффиксом.
     *
     * @param string $code Базовый код.
     *
     * @return string Свободный код.
     *
     * @throws LoreInvalidException Если суффиксы кончились.
     */
    private function availableWorldCode(string $code): string
    {
        if (!$this->worldRepository->isCodeTaken($code)) {
            return $code;
        }

        for ($suffix = 2; $suffix <= self::MAX_CODE_SUFFIX; $suffix++) {
            $candidate = $code . '-' . $suffix;
            if (!$this->worldRepository->isCodeTaken($candidate)) {
                return $candidate;
            }
        }

        throw new LoreInvalidException('Lore world code has no free suffix up to ' . self::MAX_CODE_SUFFIX);
    }

    /**
     * Есть ли раздел в документе.
     *
     * @param LoreImportDocument $document Документ.
     * @param string $code Код.
     *
     * @return bool Флаг.
     */
    private function hasSection(LoreImportDocument $document, string $code): bool
    {
        foreach ($document->getSections() as $sectionValue) {
            if ($sectionValue->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Строка раздела из документа.
     *
     * @param LoreSectionValues $sectionValue Раздел.
     *
     * @return array<string, mixed> Строка.
     */
    private function sectionRow(LoreSectionValues $sectionValue): array
    {
        return [
            'code' => $sectionValue->getCode(),
            'name' => $sectionValue->getName(),
            'description' => $sectionValue->getDescription(),
            'parentCode' => $sectionValue->getParentCode(),
            'sortOrder' => $sectionValue->getSortOrder(),
            'icon' => $sectionValue->getIcon(),
        ];
    }

    /**
     * Строка раздела из БД.
     *
     * @param LoreSectionRecord $sectionRecord Раздел.
     *
     * @return array<string, mixed> Строка.
     */
    private function sectionRowFromRecord(LoreSectionRecord $sectionRecord): array
    {
        return [
            'code' => $sectionRecord->getCode(),
            'name' => $sectionRecord->getName(),
            'description' => $sectionRecord->getDescription(),
            'parentCode' => $sectionRecord->getParentCode(),
            'sortOrder' => $sectionRecord->getSortOrder(),
            'icon' => $sectionRecord->getIcon(),
        ];
    }
}
