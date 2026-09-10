<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

/**
 * Фасад actual-строки персонажа: без схемы и без HTTP.
 */
interface ICharacters
{
    /**
     * Добавляет строку. actual_version = 1.
     *
     * @param NewCharacter $new Поля insert.
     *
     * @return int Id.
     *
     * @throws CharacterInvalidException Если имя, ревизия, JSON или секции.
     * @throws CharacterNotFoundException Если нет учётки или часов.
     */
    public function add(NewCharacter $new): int;

    /**
     * Возвращает строку по id.
     *
     * @param int $id Идентификатор.
     *
     * @return CharacterRecord Персонаж.
     *
     * @throws CharacterNotFoundException Если строки нет.
     */
    public function get(int $id): CharacterRecord;

    /**
     * Пишет choices и sheet при совпадении expectedVersion.
     *
     * @param int $id Идентификатор.
     * @param array $choices Build.
     * @param array $sheet Кэш.
     * @param int $expectedVersion Текущий actual_version.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если version меньше 1 или JSON.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    public function replacePayload(int $id, array $choices, array $sheet, int $expectedVersion): CharacterRecord;

    /**
     * Ставит active при совпадении expectedVersion.
     *
     * @param int $id Идентификатор.
     * @param bool $active Новый флаг.
     * @param int $expectedVersion Текущий actual_version.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если version меньше 1.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    public function setActive(int $id, bool $active, int $expectedVersion): CharacterRecord;
}
