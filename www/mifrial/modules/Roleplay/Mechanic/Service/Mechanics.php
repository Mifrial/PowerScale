<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Exception\MechanicNotFoundException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Mechanic\Repository\MechanicRepository;

/**
 * Фасад справочника механик.
 */
final class Mechanics implements IMechanics
{
    /**
     * Создаёт фасад.
     *
     * @param MechanicRepository $mechanicRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly MechanicRepository $mechanicRepository,
    ) {
    }

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
    public function add(string $code, string $name, string $description, string $handlerVersion): int
    {
        $trimmedCode = trim($code);
        $trimmedName = trim($name);
        $trimmedVersion = trim($handlerVersion);
        if ($trimmedCode === '' || $trimmedName === '' || $trimmedVersion === '') {
            throw new MechanicInvalidException('Mechanic code, name and handler version must not be empty');
        }

        return $this->mechanicRepository->add($trimmedCode, $trimmedName, trim($description), $trimmedVersion);
    }

    /**
     * Возвращает механику по id.
     *
     * @param int $id Идентификатор.
     *
     * @return MechanicRecord Механика.
     *
     * @throws MechanicNotFoundException Если строки нет.
     */
    public function get(int $id): MechanicRecord
    {
        return $this->mechanicRepository->getById($id);
    }

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
    public function getByCodeVersion(string $code, string $handlerVersion): MechanicRecord
    {
        $trimmedCode = trim($code);
        $trimmedVersion = trim($handlerVersion);
        if ($trimmedCode === '' || $trimmedVersion === '') {
            throw new MechanicInvalidException('Mechanic code and handler version must not be empty');
        }

        return $this->mechanicRepository->getByCodeVersion($trimmedCode, $trimmedVersion);
    }

    /**
     * Все поставки, sort id ASC.
     *
     * @return list<MechanicRecord> Каталог.
     *
     * @throws MechanicInvalidException Если выборка битая.
     */
    public function getList(): array
    {
        return $this->mechanicRepository->getList();
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
     * @throws MechanicInvalidException Если имя пусто после trim.
     * @throws MechanicNotFoundException Если строки нет.
     */
    public function update(int $id, string $name, string $description): void
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new MechanicInvalidException('Mechanic name must not be empty');
        }

        $this->mechanicRepository->getById($id);
        $this->mechanicRepository->update($id, [
            'name' => $trimmedName,
            'description' => trim($description),
        ]);
    }
}
