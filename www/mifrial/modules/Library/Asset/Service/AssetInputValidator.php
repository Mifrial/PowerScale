<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use JsonException;
use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Проверка и нормализация полей библиотеки ассетов.
 */
final class AssetInputValidator
{
    /**
     * Предел размера `meta`: объект — данные потребителя, не склад файлов.
     */
    private const META_MAX_BYTES = 8192;

    /**
     * Предел вложенности `meta`.
     */
    private const META_MAX_DEPTH = 4;

    /**
     * Проверяет ключ коллекции и возвращает нормализованный модуль.
     *
     * @param string $module Модуль-владелец.
     *
     * @return string `Group/Module`.
     *
     * @throws AssetInvalidException Если форма неверна.
     */
    public function normalizeModule(string $module): string
    {
        $normalized = trim($module);
        if (
            mb_strlen($normalized) > 64
            || preg_match('/^[A-Za-z][A-Za-z0-9_]*\/[A-Za-z][A-Za-z0-9_]*$/', $normalized) !== 1
        ) {
            throw new AssetInvalidException('Asset collection module is invalid');
        }

        return $normalized;
    }

    /**
     * Проверяет код коллекции и возвращает его нормализованным.
     *
     * @param string $code Код внутри модуля.
     *
     * @return string Код.
     *
     * @throws AssetInvalidException Если форма неверна.
     */
    public function normalizeCode(string $code): string
    {
        $normalized = trim($code);
        if (mb_strlen($normalized) > 64 || preg_match('/^[a-z][a-z0-9_]*$/', $normalized) !== 1) {
            throw new AssetInvalidException('Asset collection code is invalid');
        }

        return $normalized;
    }

    /**
     * Проверяет подпись коллекции.
     *
     * @param string $title Подпись.
     *
     * @return string Подпись без краёв.
     *
     * @throws AssetInvalidException Если пусто или длиннее 120.
     */
    public function normalizeCollectionTitle(string $title): string
    {
        $normalized = trim($title);
        if ($normalized === '' || mb_strlen($normalized) > 120) {
            throw new AssetInvalidException('Asset collection title is invalid');
        }

        return $normalized;
    }

    /**
     * Проверяет подпись ассета, при пустой берёт имя файла без расширения.
     *
     * @param string|null $requested Подпись из запроса.
     * @param string $originalName Имя файла.
     *
     * @return string Подпись.
     *
     * @throws AssetInvalidException Если подпись пуста или длиннее 120.
     */
    public function normalizeName(?string $requested, string $originalName): string
    {
        $normalized = trim((string) $requested);
        if ($normalized === '') {
            $normalized = trim(pathinfo($originalName, PATHINFO_FILENAME));
        }

        if ($normalized === '' || mb_strlen($normalized) > 120) {
            throw new AssetInvalidException('Asset name is invalid');
        }

        return $normalized;
    }

    /**
     * Проверяет подстроку поиска.
     *
     * @param string|null $searchQuery Подстрока из запроса.
     *
     * @return string|null Подстрока без краёв или null.
     *
     * @throws AssetInvalidException Если подстрока длиннее 120.
     */
    public function normalizeSearchQuery(?string $searchQuery): ?string
    {
        $normalized = trim((string) $searchQuery);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 120) {
            throw new AssetInvalidException('Asset search query is too long');
        }

        return $normalized;
    }

    /**
     * Проверяет данные потребителя и нормализует их.
     *
     * Библиотека не толкует `meta`: это объект, который кладёт потребитель.
     * Проверяется только форма — объект, ключи-строки, разумная глубина и
     * размер, — чтобы в JSON-колонку не попал мусор. Пустой объект означает
     * «данных нет» и очищает поле.
     *
     * @param array<int|string, mixed> $meta Данные из запроса.
     *
     * @return array<string, mixed>|null Объект или null.
     *
     * @throws AssetInvalidException Если форма, глубина или размер неверны.
     */
    public function normalizeMeta(array $meta): ?array
    {
        if ($meta === []) {
            return null;
        }

        if (array_is_list($meta)) {
            throw new AssetInvalidException('Asset meta must be an object');
        }

        $this->assertMetaNode($meta, 1);
        try {
            $encoded = json_encode($meta, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new AssetInvalidException('Asset meta is not JSON');
        }

        if (strlen($encoded) > self::META_MAX_BYTES) {
            throw new AssetInvalidException('Asset meta is too large');
        }

        return $meta;
    }

    /**
     * Рекурсивно проверяет ключи и глубину.
     *
     * @param array<int|string, mixed> $node Узел.
     * @param int $depth Текущая глубина.
     *
     * @return void
     *
     * @throws AssetInvalidException Если ключ или глубина неверны.
     */
    private function assertMetaNode(array $node, int $depth): void
    {
        if ($depth > self::META_MAX_DEPTH) {
            throw new AssetInvalidException('Asset meta is too deep');
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                if (is_array($item)) {
                    $this->assertMetaNode($item, $depth + 1);
                }
            }

            return;
        }

        foreach ($node as $key => $value) {
            if (!is_string($key)) {
                throw new AssetInvalidException('Asset meta keys must be strings');
            }

            if (is_array($value)) {
                $this->assertMetaNode($value, $depth + 1);
            }
        }
    }

    /**
     * Проверяет границы страницы.
     *
     * @param int $limit Размер страницы.
     * @param int $offset Сдвиг.
     *
     * @return void
     *
     * @throws AssetInvalidException Если границы неверны.
     */
    public function assertPageBounds(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0) {
            throw new AssetInvalidException('Asset page bounds are invalid');
        }
    }
}
