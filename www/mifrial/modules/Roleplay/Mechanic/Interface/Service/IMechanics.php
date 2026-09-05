<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Interface\Service;

use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;

/**
 * Фасад справочника механик: без схемы и без таблиц.
 */
interface IMechanics
{
    /**
     * Добавляет поставку механики.
     *
     * @param string $code Код семейства.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     * @param string $handlerVersion Поставка контракта.
     *
     * @return int Id.
     *
     * @throws MechanicInvalidException Если поля пусты или пара code+version занята.
     */
    public function add(string $code, string $name, string $description, string $handlerVersion): int;

    /**
     * Возвращает механику по id.
     *
     * @param int $id Идентификатор.
     *
     * @return MechanicRecord Механика.
     *
     * @throws MechanicNotFoundException Если строки нет.
     */
    public function get(int $id): MechanicRecord;

    /**
     * Возвращает механику по семейству и поставке.
     *
     * @param string $code Код семейства.
     * @param string $handlerVersion Поставка контракта.
     *
     * @return MechanicRecord Механика.
     *
     * @throws MechanicInvalidException Если code или version пусты после trim.
     * @throws MechanicNotFoundException Если пары нет.
     */
    public function getByCodeVersion(string $code, string $handlerVersion): MechanicRecord;

    /**
     * Все поставки, sort id ASC.
     *
     * @return list<MechanicRecord> Каталог.
     *
     * @throws MechanicInvalidException Если выборка битая.
     */
    public function getList(): array;

    /**
     * Пишет name и description. code и handler_version не меняются.
     *
     * @param int $id Идентификатор.
     * @param string $name Подпись.
     * @param string $description Текст; пустая строка допустима.
     *
     * @return void
     *
     * @throws MechanicInvalidException Если имя пусто после trim.
     * @throws MechanicNotFoundException Если строки нет.
     */
    public function update(int $id, string $name, string $description): void;
}
