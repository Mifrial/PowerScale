<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Service;

use Mifrial\Roleplay\Keyword\Dto\KeywordRecord;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Repository\KeywordRepository;

/**
 * Фасад справочника признаков.
 */
final class Keywords implements IKeywords
{
    /**
     * Создаёт фасад.
     *
     * @param KeywordRepository $keywordRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly KeywordRepository $keywordRepository,
    ) {
    }

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
    public function add(string $code, string $name, string $description = '', bool $active = true): int
    {
        $trimmedCode = trim($code);
        $trimmedName = trim($name);
        if ($trimmedCode === '' || $trimmedName === '') {
            throw new KeywordInvalidException('Keyword code and name must not be empty');
        }

        return $this->keywordRepository->add($trimmedCode, $trimmedName, trim($description), $active);
    }

    /**
     * Возвращает признак по id.
     *
     * @param int $id Идентификатор.
     *
     * @return KeywordRecord Признак.
     *
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function get(int $id): KeywordRecord
    {
        return $this->keywordRepository->getById($id);
    }

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
    public function getByCode(string $code): KeywordRecord
    {
        $trimmedCode = trim($code);
        if ($trimmedCode === '') {
            throw new KeywordInvalidException('Keyword code must not be empty');
        }

        return $this->keywordRepository->getByCode($trimmedCode);
    }

    /**
     * Все признаки, sort id ASC.
     *
     * @return list<KeywordRecord> Каталог.
     *
     * @throws KeywordInvalidException Если выборка битая.
     */
    public function getList(): array
    {
        return $this->keywordRepository->getList();
    }

    /**
     * Пишет name и description.
     *
     * @param int $id Идентификатор.
     * @param string $name Подпись.
     * @param string $description Текст.
     *
     * @return void
     *
     * @throws KeywordInvalidException Если имя пусто после trim.
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function update(int $id, string $name, string $description): void
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new KeywordInvalidException('Keyword name must not be empty');
        }

        $this->keywordRepository->getById($id);
        $this->keywordRepository->update($id, [
            'name' => $trimmedName,
            'description' => trim($description),
        ]);
    }

    /**
     * Выключает признак.
     *
     * @param int $id Идентификатор.
     *
     * @return void
     *
     * @throws KeywordNotFoundException Если строки нет.
     */
    public function deactivate(int $id): void
    {
        $keywordRecord = $this->keywordRepository->getById($id);
        if (!$keywordRecord->isActive()) {
            return;
        }

        $this->keywordRepository->update($id, ['active' => false]);
    }
}
