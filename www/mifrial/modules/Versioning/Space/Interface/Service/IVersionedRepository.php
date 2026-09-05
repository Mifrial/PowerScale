<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Interface\Service;

use Mifrial\Versioning\Space\Dto\CommitEntry;
use Mifrial\Versioning\Space\Dto\RevisionRecord;
use Mifrial\Versioning\Space\Dto\RevisionSlice;
use Mifrial\Versioning\Space\Dto\RevisionSummary;
use Mifrial\Versioning\Space\Dto\SpaceRecord;
use Mifrial\Versioning\Space\Exception\SpaceInvalidException;
use Mifrial\Versioning\Space\Exception\SpaceNotFoundException;

/**
 * Часы и срез одной версионируемой сущности.
 */
interface IVersionedRepository
{
    /**
     * Создаёт пространство репозитория.
     *
     * @param string $title Подпись.
     *
     * @return int Идентификатор пространства.
     *
     * @throws SpaceInvalidException Если title пуст после trim.
     */
    public function addSpace(string $title): int;

    /**
     * Читает пространство.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return SpaceRecord Пространство.
     *
     * @throws SpaceNotFoundException Если строки нет.
     */
    public function getSpace(int $spaceId): SpaceRecord;

    /**
     * Пишет подпись пространства.
     *
     * @param int $spaceId Идентификатор.
     * @param string $title Подпись.
     *
     * @return void
     *
     * @throws SpaceInvalidException Если title пуст после trim.
     * @throws SpaceNotFoundException Если строки нет.
     */
    public function updateSpace(int $spaceId, string $title): void;

    /**
     * Публикует новый состав.
     *
     * @param int $spaceId Пространство.
     * @param array<int, CommitEntry> $entries List keep/create/change.
     * @param bool $allowUnchangedComposition Разрешить тот же набор version_id.
     *
     * @return RevisionRecord Новая ревизия.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     * @throws SpaceInvalidException Если вход или состав недопустимы.
     */
    public function commit(
        int $spaceId,
        array $entries,
        bool $allowUnchangedComposition = false,
    ): RevisionRecord;

    /**
     * Возвращает опубликованный срез.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер ревизии.
     *
     * @return RevisionSlice Состав.
     *
     * @throws SpaceInvalidException Если номер меньше 1.
     * @throws SpaceNotFoundException Если ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RevisionSlice;

    /**
     * Последняя ревизия пространства или null.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return RevisionRecord|null Ревизия.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     */
    public function findLatestRevision(int $spaceId): ?RevisionRecord;

    /**
     * Лента ревизий: новые сверху, без пунктов состава.
     *
     * @param int $spaceId Идентификатор.
     *
     * @return array<int, RevisionSummary> Сводки.
     *
     * @throws SpaceNotFoundException Если пространства нет.
     * @throws SpaceInvalidException Если состав ревизии пуст.
     */
    public function getRevisionList(int $spaceId): array;
}
