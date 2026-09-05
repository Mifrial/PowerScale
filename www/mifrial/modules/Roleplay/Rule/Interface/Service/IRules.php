<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Interface\Service;

use Mifrial\Roleplay\Rule\Dto\RuleCommitEntry;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionRecord;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSlice;
use Mifrial\Roleplay\Rule\Dto\RuleRevisionSummary;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleNotFoundException;

/**
 * Фасад правил: identity по code, состав на часах.
 */
interface IRules
{
    /**
     * Создаёт пространство.
     *
     * @param string $label Подпись часов.
     *
     * @return int Space id.
     *
     * @throws RuleInvalidException Если пусто после trim.
     */
    public function addSpace(string $label): int;

    /**
     * Пишет подпись часов.
     *
     * @param int $spaceId Пространство.
     * @param string $label Подпись.
     *
     * @return void
     *
     * @throws RuleInvalidException Если пусто после trim.
     * @throws RuleNotFoundException Если пространства нет.
     */
    public function updateSpace(int $spaceId, string $label): void;

    /**
     * Публикует полный состав.
     *
     * @param int $spaceId Пространство.
     * @param array<int, RuleCommitEntry> $entries Keep/put.
     * @param bool $allowUnchangedComposition Разрешить тот же набор version_id.
     *
     * @return RuleRevisionRecord Ревизия.
     *
     * @throws RuleInvalidException Если состав недопустим.
     * @throws RuleNotFoundException Если пространства нет.
     */
    public function commit(
        int $spaceId,
        array $entries,
        bool $allowUnchangedComposition = false,
    ): RuleRevisionRecord;

    /**
     * Срез ревизии.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     *
     * @return RuleRevisionSlice Состав.
     *
     * @throws RuleInvalidException Если номер меньше 1.
     * @throws RuleNotFoundException Если ревизии нет.
     */
    public function getRevision(int $spaceId, int $revision): RuleRevisionSlice;

    /**
     * Правило с code в составе ревизии.
     *
     * @param int $spaceId Пространство.
     * @param int $revision Номер.
     * @param string $code Ключ.
     *
     * @return RuleVersionRecord Экземпляр.
     *
     * @throws RuleInvalidException Если code пуст.
     * @throws RuleNotFoundException Если нет в составе.
     */
    public function findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord;

    /**
     * Последняя ревизия часов или null.
     *
     * @param int $spaceId Пространство.
     *
     * @return RuleRevisionRecord|null Ревизия без пунктов состава.
     *
     * @throws RuleNotFoundException Если пространства часов нет.
     */
    public function findLatestRevision(int $spaceId): ?RuleRevisionRecord;

    /**
     * Лента ревизий без пунктов состава.
     *
     * @param int $spaceId Пространство.
     *
     * @return array<int, RuleRevisionSummary> Сводки.
     *
     * @throws RuleNotFoundException Если пространства нет.
     * @throws RuleInvalidException Если запись часов битая.
     */
    public function getRevisionList(int $spaceId): array;
}
