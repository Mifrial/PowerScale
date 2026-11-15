<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Service;

// phpcs:disable MifrialCodingStandard.Metrics.MethodQuality.MethodTooLong -- сборка снимка/фильтра и разбор полей остаются одним сценарием.

use Mifrial\Roleplay\Lore\Dto\LoreArticleValues;
use Mifrial\Roleplay\Lore\Exception\LoreInvalidException;

/**
 * Проверка снимка статьи.
 */
final class LoreArticleValidator
{
    private const MAX_BODY_BYTES = 60000;

    private const MAX_SUMMARY = 2000;

    private const MAX_KEYWORDS = 50;

    private const MAX_READERS = 500;

    private const STATUSES = ['draft', 'published'];

    private const VISIBILITIES = ['public', 'secret'];

    private const ALLOWED_KEYS = [
        'code',
        'title',
        'summary',
        'bodyHtml',
        'sectionCode',
        'sortOrder',
        'keywordIds',
        'status',
        'visibility',
        'readers',
    ];

    /**
     * Проверяет снимок статьи.
     *
     * @param array<string|int, mixed> $payload Снимок.
     *
     * @return LoreArticleValues Поля для записи.
     *
     * @throws LoreInvalidException Если снимок недопустим.
     */
    public function validate(array $payload): LoreArticleValues
    {
        LorePayload::assertKnownKeys($payload, self::ALLOWED_KEYS);
        $code = LoreCode::requireValid(
            trim(LorePayload::requireString($payload, 'code')),
            'Lore article code',
        );
        $title = $this->title($payload);
        $summary = LorePayload::optionalString($payload, 'summary');
        if (mb_strlen($summary, 'UTF-8') > self::MAX_SUMMARY) {
            throw new LoreInvalidException('Lore article summary is too long');
        }

        $bodyHtml = LorePayload::optionalString($payload, 'bodyHtml');
        if (strlen($bodyHtml) > self::MAX_BODY_BYTES) {
            throw new LoreInvalidException('Lore article body is larger than ' . self::MAX_BODY_BYTES . ' bytes');
        }

        $sectionCode = $this->sectionCode($payload);
        $sortOrder = LorePayload::intValue($payload, 'sortOrder');
        if ($sortOrder < 0) {
            throw new LoreInvalidException('Lore article sortOrder must be non-negative');
        }

        $keywordIds = $this->positiveIds($payload, 'keywordIds', self::MAX_KEYWORDS);
        $readerIds = $this->positiveIds($payload, 'readers', self::MAX_READERS);
        $status = $this->enumValue($payload, 'status', self::STATUSES, 'draft');
        $visibility = $this->enumValue($payload, 'visibility', self::VISIBILITIES, 'public');

        return new LoreArticleValues(
            $code,
            $title,
            $summary,
            $bodyHtml,
            LoreBodyText::derive($bodyHtml),
            $sectionCode,
            $sortOrder,
            $keywordIds,
            $status,
            $visibility,
            $readerIds,
        );
    }

    /**
     * Заголовок 1..255.
     *
     * @param array<string|int, mixed> $payload Снимок.
     *
     * @return string Заголовок.
     *
     * @throws LoreInvalidException Если заголовок пуст или длинен.
     */
    private function title(array $payload): string
    {
        $title = trim(LorePayload::requireString($payload, 'title'));
        if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
            throw new LoreInvalidException('Lore article title must be 1..255 characters');
        }

        return $title;
    }

    /**
     * Код раздела или null.
     *
     * @param array<string|int, mixed> $payload Снимок.
     *
     * @return string|null Код или null.
     *
     * @throws LoreInvalidException Если код недопустим.
     */
    private function sectionCode(array $payload): ?string
    {
        $sectionCode = LorePayload::stringOrNull($payload, 'sectionCode');
        if ($sectionCode === null) {
            return null;
        }

        $sectionCode = trim($sectionCode);
        if ($sectionCode === '') {
            return null;
        }

        return LoreCode::requireValid($sectionCode, 'Lore article sectionCode');
    }

    /**
     * Значение из списка.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     * @param array<int, string> $allowed Допустимые значения.
     * @param string $default Значение по умолчанию.
     *
     * @return string Значение.
     *
     * @throws LoreInvalidException Если значение недопустимо.
     */
    private function enumValue(array $payload, string $key, array $allowed, string $default): string
    {
        $value = LorePayload::optionalString($payload, $key, $default);
        if (!in_array($value, $allowed, true)) {
            throw new LoreInvalidException('Lore article ' . $key . ' has an unsupported value');
        }

        return $value;
    }

    /**
     * Список положительных id.
     *
     * @param array<string|int, mixed> $payload Снимок.
     * @param string $key Поле.
     * @param int $limit Потолок.
     *
     * @return array<int, int> Id.
     *
     * @throws LoreInvalidException Если id недопустим.
     */
    private function positiveIds(array $payload, string $key, int $limit): array
    {
        $ids = LorePayload::intList($payload, $key);
        if (count($ids) > $limit) {
            throw new LoreInvalidException('Lore ' . $key . ' limit is ' . $limit);
        }

        foreach ($ids as $id) {
            if ($id < 1) {
                throw new LoreInvalidException('Lore ' . $key . ' must contain positive ids');
            }
        }

        return $ids;
    }
}
