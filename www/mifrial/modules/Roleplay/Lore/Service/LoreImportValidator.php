<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong,MifrialCodingStandard.Metrics.MethodQuality.CognitiveComplexityTooHigh,MifrialCodingStandard.Metrics.MethodQuality.CyclomaticComplexityTooHigh -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Roleplay\Lore\Dto\LoreArticleValues;
use Mifrial\Roleplay\Lore\Dto\LoreImportArticle;
use Mifrial\Roleplay\Lore\Dto\LoreImportDocument;
use Mifrial\Roleplay\Lore\Dto\LoreKeywordSnapshot;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * Проверка файла `powerscale.lore` до записи.
 */
final class LoreImportValidator
{
    private const FORMAT = 'powerscale.lore';

    private const FORMAT_VERSION = 1;

    private const MAX_ARTICLES = 5000;

    private const MAX_KEYWORDS = 2000;

    private const ROOT_KEYS = [
        'format',
        'formatVersion',
        'exportedAt',
        'world',
        'source',
        'sections',
        'keywords',
        'articles',
    ];

    private const WORLD_KEYS = ['code', 'name', 'description', 'visibility', 'spaceCode'];

    private const KEYWORD_KEYS = ['code', 'name', 'description', 'active'];

    private const ARTICLE_KEYS = [
        'code',
        'title',
        'summary',
        'bodyHtml',
        'sectionCode',
        'sortOrder',
        'keywordCodes',
        'status',
        'visibility',
        'readers',
        'isArchived',
    ];

    /**
     * Создаёт проверку.
     *
     * @param LoreWorldValidator $worldValidator Проверка мира.
     * @param LoreTreeValidator $treeValidator Проверка дерева.
     * @param LoreArticleValidator $articleValidator Проверка статьи.
     * @param LoreImportHtml $importHtml Чистка HTML.
     *
     * @return void
     */
    public function __construct(
        private readonly LoreWorldValidator $worldValidator,
        private readonly LoreTreeValidator $treeValidator,
        private readonly LoreArticleValidator $articleValidator,
        private readonly LoreImportHtml $importHtml,
    ) {
    }

    /**
     * Проверяет документ.
     *
     * @param array<string|int, mixed> $payload Документ.
     *
     * @return LoreImportDocument Проверенный документ.
     *
     * @throws LoreInvalidException Если документ недопустим.
     */
    public function validate(array $payload): LoreImportDocument
    {
        LorePayload::assertKnownKeys($payload, self::ROOT_KEYS);
        $this->assertFormat($payload);
        $world = $this->object($payload, 'world');
        LorePayload::assertKnownKeys($world, self::WORLD_KEYS);
        $worldValues = $this->worldValidator->validate($world);

        $sections = $this->list($payload, 'sections');
        $sectionValues = $this->treeValidator->validate($sections);
        $sectionCodes = [];
        foreach ($sectionValues as $sectionValue) {
            $sectionCodes[$sectionValue->getCode()] = true;
        }

        $keywords = $this->keywords($payload);
        $keywordCodes = [];
        foreach ($keywords as $keyword) {
            $keywordCodes[$keyword->getCode()] = true;
        }

        $warnings = [];
        $articles = $this->articles($payload, $sectionCodes, $keywordCodes, $warnings);

        return new LoreImportDocument($worldValues, $sectionValues, $keywords, $articles, $warnings);
    }

    /**
     * Проверяет формат и версию файла.
     *
     * @param array<string|int, mixed> $payload Документ.
     *
     * @return void
     *
     * @throws LoreInvalidException Если формат не тот.
     */
    private function assertFormat(array $payload): void
    {
        if (($payload['format'] ?? null) !== self::FORMAT) {
            throw new LoreInvalidException('Lore file format must be ' . self::FORMAT);
        }

        if (($payload['formatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new LoreInvalidException('Lore file format version must be ' . self::FORMAT_VERSION);
        }
    }

    /**
     * Признаки файла.
     *
     * @param array<string|int, mixed> $payload Документ.
     *
     * @return array<int, LoreKeywordSnapshot> Снимки.
     *
     * @throws LoreInvalidException Если список недопустим.
     */
    private function keywords(array $payload): array
    {
        $rows = $this->list($payload, 'keywords');
        if (count($rows) > self::MAX_KEYWORDS) {
            throw new LoreInvalidException('Lore file has too many keywords');
        }

        $keywords = [];
        $seen = [];
        foreach ($rows as $row) {
            $keyword = $this->keyword($row);
            if (isset($seen[$keyword->getCode()])) {
                throw new LoreInvalidException('Lore keyword code is duplicated: ' . $keyword->getCode());
            }

            $seen[$keyword->getCode()] = true;
            $keywords[] = $keyword;
        }

        return $keywords;
    }

    /**
     * Снимок признака.
     *
     * @param mixed $row Строка файла.
     *
     * @return LoreKeywordSnapshot Снимок.
     *
     * @throws LoreInvalidException Если строка недопустима.
     */
    private function keyword(mixed $row): LoreKeywordSnapshot
    {
        if (!is_array($row)) {
            throw new LoreInvalidException('Lore keyword must be an object');
        }

        LorePayload::assertKnownKeys($row, self::KEYWORD_KEYS);
        $code = LoreCode::requireValid(
            trim(LorePayload::requireString($row, 'code')),
            'Lore keyword code',
        );
        $name = trim(LorePayload::requireString($row, 'name'));
        if ($name === '' || mb_strlen($name, 'UTF-8') > 255) {
            throw new LoreInvalidException('Lore keyword name must be 1..255 characters');
        }

        $description = LorePayload::optionalString($row, 'description');
        if (mb_strlen($description, 'UTF-8') > 2000) {
            throw new LoreInvalidException('Lore keyword description is too long');
        }

        $active = $row['active'] ?? true;
        if (!is_bool($active)) {
            throw new LoreInvalidException('Lore keyword active must be a boolean');
        }

        return new LoreKeywordSnapshot($code, $name, $description, $active);
    }

    /**
     * Статьи файла.
     *
     * @param array<string|int, mixed> $payload Документ.
     * @param array<string, true> $sectionCodes Разделы файла.
     * @param array<string, true> $keywordCodes Признаки файла.
     * @param array<int, array{code: string, path: string, message: string}> $warnings Предупреждения.
     *
     * @return array<int, LoreImportArticle> Статьи.
     *
     * @throws LoreInvalidException Если статья недопустима.
     */
    private function articles(
        array $payload,
        array $sectionCodes,
        array $keywordCodes,
        array &$warnings,
    ): array {
        $rows = $this->list($payload, 'articles');
        if (count($rows) > self::MAX_ARTICLES) {
            throw new LoreInvalidException('Lore file has too many articles');
        }

        $articles = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new LoreInvalidException('Lore article must be an object');
            }

            $path = 'articles[' . $index . ']';
            LorePayload::assertKnownKeys($row, self::ARTICLE_KEYS);
            $clean = $this->importHtml->clean(LorePayload::optionalString($row, 'bodyHtml'));
            foreach ($clean['warnings'] as $warningCode) {
                $warnings[] = [
                    'code' => 'html-' . $warningCode,
                    'path' => $path . '.bodyHtml',
                    'message' => 'HTML is cleaned on import: ' . $warningCode,
                ];
            }

            $articleInput = $row;
            $articleInput['bodyHtml'] = $clean['html'];
            $articleInput['keywordIds'] = [];
            unset($articleInput['keywordCodes'], $articleInput['readers'], $articleInput['isArchived']);
            $values = $this->articleValidator->validate($articleInput);
            if (isset($seen[$values->getCode()])) {
                throw new LoreInvalidException('Lore article code is duplicated: ' . $values->getCode());
            }

            $sectionCode = $values->getSectionCode();
            if ($sectionCode !== null && !isset($sectionCodes[$sectionCode])) {
                throw new LoreInvalidException('Lore article ' . $values->getCode() . ' uses unknown section');
            }

            $articleKeywordCodes = $this->codeList($row, 'keywordCodes', 50);
            foreach ($articleKeywordCodes as $articleKeywordCode) {
                if (!isset($keywordCodes[$articleKeywordCode])) {
                    $warnings[] = [
                        'code' => 'keyword-missing',
                        'path' => $path . '.keywordCodes',
                        'message' => 'Keyword ' . $articleKeywordCode . ' has no snapshot and is skipped',
                    ];
                }
            }

            $articleKeywordCodes = array_values(array_filter(
                $articleKeywordCodes,
                static fn (string $articleKeywordCode): bool => isset($keywordCodes[$articleKeywordCode]),
            ));

            $isArchived = $row['isArchived'] ?? false;
            if (!is_bool($isArchived)) {
                throw new LoreInvalidException('Lore article isArchived must be a boolean');
            }

            $seen[$values->getCode()] = true;
            $articles[] = new LoreImportArticle(
                new LoreArticleValues(
                    $values->getCode(),
                    $values->getTitle(),
                    $values->getSummary(),
                    $values->getBodyHtml(),
                    $values->getBodyText(),
                    $values->getSectionCode(),
                    $values->getSortOrder(),
                    [],
                    $values->getStatus(),
                    $values->getVisibility(),
                    [],
                ),
                $articleKeywordCodes,
                $this->loginList($row),
                $isArchived,
            );
        }

        return $articles;
    }

    /**
     * Список кодов.
     *
     * @param array<string|int, mixed> $row Статья файла.
     * @param string $key Поле.
     * @param int $limit Потолок.
     *
     * @return array<int, string> Коды.
     *
     * @throws LoreInvalidException Если список недопустим.
     */
    private function codeList(array $row, string $key, int $limit): array
    {
        $value = $row[$key] ?? [];
        if (!is_array($value) || !array_is_list($value) || count($value) > $limit) {
            throw new LoreInvalidException('Lore article ' . $key . ' must be a list of at most ' . $limit);
        }

        $codes = [];
        foreach ($value as $code) {
            if (!is_string($code)) {
                throw new LoreInvalidException('Lore article ' . $key . ' must contain strings');
            }

            $codes[LoreCode::requireValid(trim($code), 'Lore ' . $key . ' item')] = true;
        }

        return array_keys($codes);
    }

    /**
     * Логины читателей.
     *
     * @param array<string|int, mixed> $row Статья файла.
     *
     * @return array<int, string> Логины.
     *
     * @throws LoreInvalidException Если список недопустим.
     */
    private function loginList(array $row): array
    {
        $value = $row['readers'] ?? [];
        if (!is_array($value) || !array_is_list($value) || count($value) > 500) {
            throw new LoreInvalidException('Lore article readers must be a list of at most 500 logins');
        }

        $logins = [];
        foreach ($value as $login) {
            if (!is_string($login) || trim($login) === '' || mb_strlen($login, 'UTF-8') > 255) {
                throw new LoreInvalidException('Lore article reader must be a login');
            }

            $logins[trim($login)] = true;
        }

        return array_keys($logins);
    }

    /**
     * JSON-объект по ключу.
     *
     * @param array<string|int, mixed> $payload Документ.
     * @param string $key Поле.
     *
     * @return array<string|int, mixed> Объект.
     *
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    private function object(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value) || array_is_list($value)) {
            throw new LoreInvalidException('Lore file ' . $key . ' must be an object');
        }

        return $value;
    }

    /**
     * JSON-список по ключу.
     *
     * @param array<string|int, mixed> $payload Документ.
     * @param string $key Поле.
     *
     * @return array<int, mixed> Список.
     *
     * @throws LoreInvalidException Если поля нет или тип неверен.
     */
    private function list(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new LoreInvalidException('Lore file ' . $key . ' must be a list');
        }

        return $value;
    }
}
