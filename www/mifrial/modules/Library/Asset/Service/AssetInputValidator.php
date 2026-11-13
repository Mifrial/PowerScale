<?php

declare(strict_types=1);

namespace Mifrial\Library\Asset\Service;

use Mifrial\Library\Asset\Exception\AssetInvalidException;

/**
 * Проверка и нормализация полей библиотеки ассетов.
 */
final class AssetInputValidator
{
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
