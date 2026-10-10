<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\ResourceBackfillResult;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;

/**
 * Точечная запись actual: операция и ожидаемая actual_version.
 */
interface ICharacterActualMutations
{
    /**
     * Пишет actual, если версия совпала и операции разобраны.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Текущий actual_version.
     * @param array $operations Список операций.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterConflictException Если версия устарела.
     * @throws CharacterInvalidException Если операция или форма листа.
     * @throws CharacterNotFoundException Если строки или ревизии нет.
     * @throws CharacterSaveRejectedException Если validate вернул problems.
     */
    public function apply(int $characterId, int $expectedActualVersion, array $operations): CharacterRecord;

    /**
     * Инициализирует или выправляет resource rows через expected version.
     *
     * @param int $characterId Персонаж.
     * @param int $expectedActualVersion Ожидаемая actual_version.
     *
     * @return ResourceBackfillResult Результат backfill.
     *
     * @throws CharacterConflictException Если версия устарела.
     * @throws CharacterInvalidException Если лист или resource row битые.
     * @throws CharacterNotFoundException Если строки или ревизии нет.
     * @throws CharacterSaveRejectedException Если validate вернул problems.
     */
    public function backfill(int $characterId, int $expectedActualVersion): ResourceBackfillResult;

    /**
     * Патчит документ без записи строки character.
     *
     * @param int $spaceId Мир листа.
     * @param int $rulesRevision Ревизия листа.
     * @param array $choices Сохранённые choices.
     * @param array $sheet Сохранённый sheet.
     * @param array $operations Список операций.
     *
     * @return array{choices: array, sheet: array} Документ.
     *
     * @throws CharacterInvalidException Если операция или tombstone.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function applyToDocument(
        int $spaceId,
        int $rulesRevision,
        array $choices,
        array $sheet,
        array $operations,
    ): array;
}
