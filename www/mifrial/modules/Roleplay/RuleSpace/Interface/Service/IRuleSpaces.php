<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Один оператор мира: два входа публикации не склеивать в tagged union.

namespace Mifrial\Roleplay\RuleSpace\Interface\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSummary;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceCatalog;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpacePatch;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceRecord;
use Mifrial\Roleplay\RuleSpace\Dto\RuleSpaceSelection;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceInvalidException;
use Mifrial\Roleplay\RuleSpace\Exception\RuleSpaceNotFoundException;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// Один оператор мира: два входа публикации не склеивать в tagged union.

/**
 * Оператор миров правил: sidecar и состав через IRules.
 */
interface IRuleSpaces
{
    /**
     * Создаёт мир, опционально копируя состав.
     *
     * @param string $code Ключ URL.
     * @param string $name Подпись.
     * @param int $ownerUserId Владелец.
     * @param string $description Текст.
     * @param int|null $inheritFromSpaceId Родительский мир.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если вход недопустим.
     * @throws RuleSpaceNotFoundException Если родителя нет.
     */
    public function add(
        string $code,
        string $name,
        int $ownerUserId,
        string $description = '',
        ?int $inheritFromSpaceId = null,
    ): RuleSpaceRecord;

    /**
     * Мир по id часов.
     *
     * @param int $spaceId Space id.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function get(int $spaceId): RuleSpaceRecord;

    /**
     * Мир по коду.
     *
     * @param string $code Ключ URL.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если code пуст.
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function getByCode(string $code): RuleSpaceRecord;

    /**
     * Обновляет переданные поля мета.
     *
     * @param int $spaceId Мир.
     * @param RuleSpacePatch $patch Присутствующие свойства.
     *
     * @return RuleSpaceRecord Мир.
     *
     * @throws RuleSpaceInvalidException Если patch пуст или name пуст.
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function update(int $spaceId, RuleSpacePatch $patch): RuleSpaceRecord;

    /**
     * Выключает мир в продукте.
     *
     * @param int $spaceId Мир.
     *
     * @return void
     *
     * @throws RuleSpaceNotFoundException Если sidecar нет.
     */
    public function deactivate(int $spaceId): void;

    /**
     * Срез ревизии мира.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleRevisionSlice Состав.
     *
     * @throws RuleSpaceInvalidException Если номер меньше 1.
     * @throws RuleSpaceNotFoundException Если мира или ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RuleRevisionSlice;

    /**
     * Лента ревизий мира.
     *
     * @param int $spaceId Мир.
     *
     * @return array<int, RuleRevisionSummary> Сводки.
     *
     * @throws RuleSpaceNotFoundException Если мира нет.
     * @throws RuleSpaceInvalidException Если запись битая.
     */
    public function getRevisionList(int $spaceId): array;

    /**
     * Правило с code в составе.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     * @param string $code Ключ правила.
     *
     * @return RuleVersionRecord Экземпляр.
     *
     * @throws RuleSpaceInvalidException Если code пуст.
     * @throws RuleSpaceNotFoundException Если нет мира или пункта.
     */
    public function findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord;

    /**
     * Снимок каталога ревизии.
     *
     * @param int $spaceId Мир.
     * @param int $revision Номер.
     *
     * @return RuleSpaceCatalog Снимок.
     *
     * @throws RuleSpaceNotFoundException Если мира нет.
     */
    public function getCatalog(int $spaceId, int $revision): RuleSpaceCatalog;

    /**
     * Публикует полный состав мира.
     *
     * @param int $spaceId Мир.
     * @param array<int, RuleCommitEntry> $entries Keep/put.
     * @param RuleSpaceCatalog|null $catalog Явный снимок или шаринг.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleSpaceInvalidException Если состав недопустим.
     * @throws RuleSpaceNotFoundException Если мира нет.
     */
    public function commit(
        int $spaceId,
        array $entries,
        ?RuleSpaceCatalog $catalog = null,
    ): RuleRevisionRecord;

    /**
     * Публикует выбранные put и tombstone относительно базы.
     *
     * @param int $spaceId Мир.
     * @param RuleSpaceSelection $selection База и выбор.
     * @param RuleSpaceCatalog|null $catalog Явный снимок или шаринг.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleSpaceInvalidException Если выбор или состав недопустимы.
     * @throws RuleSpaceNotFoundException Если мира или базы нет.
     */
    public function commitSelected(
        int $spaceId,
        RuleSpaceSelection $selection,
        ?RuleSpaceCatalog $catalog = null,
    ): RuleRevisionRecord;
}
