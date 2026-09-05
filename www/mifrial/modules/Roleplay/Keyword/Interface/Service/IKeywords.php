<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Interface\Service;

use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;

/**
 * Фасад справочника признаков: без схемы и без таблиц.
 */
interface IKeywords
{
    /**
     * Добавляет признак.
     *
     * @param string $code Семантический ключ.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     * @param bool $active Включён в справочнике.
     *
     * @return int Id.
     *
     * @throws KeywordInvalidException Если код/имя пусты или code занят.
     */
    public function add(string $code, string $name, string $description = '', bool $active = true): int;

    /**
     * Возвращает признак по id.
     *
     * @param int $id Идентификатор.
     *
     * @return KeywordRecord Признак.
     *
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function get(int $id): KeywordRecord;

    /**
     * Возвращает признак по коду.
     *
     * @param string $code Семантический ключ.
     *
     * @return KeywordRecord Признак.
     *
     * @throws KeywordInvalidException Если код пуст после trim.
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function getByCode(string $code): KeywordRecord;

    /**
     * Все признаки, sort id ASC.
     *
     * @return list<KeywordRecord> Каталог.
     *
     * @throws KeywordInvalidException Если выборка битая.
     */
    public function getList(): array;

    /**
     * Пишет name и description. code не меняется.
     *
     * @param int $id Идентификатор.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     *
     * @return void
     *
     * @throws KeywordInvalidException Если имя пусто после trim.
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function update(int $id, string $name, string $description): void;

    /**
     * Выключает признак. Идемпотентно.
     *
     * @param int $id Идентификатор.
     *
     * @return void
     *
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function deactivate(int $id): void;
}
