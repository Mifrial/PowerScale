<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Mechanic\Dto\Action\CreateMechanicInput;
use Mifrial\Roleplay\Mechanic\Dto\Action\UpdateMechanicInput;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Exception\MechanicInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * HTTP-сценарии справочника механик: актор, фасад, JSON.
 */
final class MechanicHttpService
{
    private const LIST_LIMIT = 500;

    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param IMechanics $mechanics Фасад.
     * @param MechanicViewAssembler $viewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly IMechanics $mechanics,
        private readonly MechanicViewAssembler $viewAssembler,
    ) {
    }

    /**
     * Каталог поставок.
     *
     * @return array<int, array<string, mixed>> Mechanic[].
     *
     * @throws ActionException AUTH_REQUIRED.
     * @throws MechanicInvalidException Если строк больше капа.
     */
    public function getList(): array
    {
        $this->userAccess->requireActor();
        $records = $this->mechanics->getList();
        if (count($records) > self::LIST_LIMIT) {
            throw new MechanicInvalidException('Mechanic catalog exceeds list cap');
        }

        $views = [];
        foreach ($records as $mechanicRecord) {
            $views[] = $this->viewAssembler->assemble($mechanicRecord);
        }

        return $views;
    }

    /**
     * Поставка по id.
     *
     * @param int $id Mechanic id.
     *
     * @return array<string, mixed> Mechanic.
     *
     * @throws ActionException AUTH_REQUIRED.
     */
    public function get(int $id): array
    {
        $this->userAccess->requireActor();

        return $this->viewAssembler->assemble($this->mechanics->get($id));
    }

    /**
     * Создаёт поставку.
     *
     * @param CreateMechanicInput $input JSON.
     *
     * @return array<string, mixed> Mechanic.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     */
    public function create(CreateMechanicInput $input): array
    {
        $this->userAccess->requireKey(MechanicPermissionKeys::CREATE);
        $mechanicId = $this->mechanics->add($input->code, $input->name, $input->description, $input->version);

        return $this->viewAssembler->assemble($this->mechanics->get($mechanicId));
    }

    /**
     * Пишет name и/или description.
     *
     * @param UpdateMechanicInput $input JSON.
     *
     * @return array<string, mixed> Mechanic.
     *
     * @throws ActionException AUTH_REQUIRED или AUTH_DENIED.
     * @throws MechanicInvalidException Если нет полей или JSON null.
     */
    public function update(UpdateMechanicInput $input): array
    {
        $this->userAccess->requireKey(MechanicPermissionKeys::EDIT);
        if (!$input->name->isPresent() && !$input->description->isPresent()) {
            throw new MechanicInvalidException('Mechanic update needs a field');
        }

        $current = $this->mechanics->get($input->id);
        $this->mechanics->update(
            $input->id,
            $this->mergedName($input, $current),
            $this->mergedDescription($input, $current),
        );

        return $this->viewAssembler->assemble($this->mechanics->get($input->id));
    }

    /**
     * Имя patch или текущее.
     *
     * @param UpdateMechanicInput $input JSON.
     * @param MechanicRecord $current Строка.
     *
     * @return string Имя.
     *
     * @throws MechanicInvalidException Если JSON null.
     */
    private function mergedName(UpdateMechanicInput $input, MechanicRecord $current): string
    {
        if (!$input->name->isPresent()) {
            return $current->getName();
        }

        return $this->presentString($input->name->getValue());
    }

    /**
     * Описание patch или текущее.
     *
     * @param UpdateMechanicInput $input JSON.
     * @param MechanicRecord $current Строка.
     *
     * @return string Текст.
     *
     * @throws MechanicInvalidException Если JSON null.
     */
    private function mergedDescription(UpdateMechanicInput $input, MechanicRecord $current): string
    {
        if (!$input->description->isPresent()) {
            return $current->getDescription();
        }

        return $this->presentString($input->description->getValue());
    }

    /**
     * Строка JSON, не null.
     *
     * @param string|null $value JSON.
     *
     * @return string Строка.
     *
     * @throws MechanicInvalidException Если null.
     */
    private function presentString(?string $value): string
    {
        if ($value === null) {
            throw new MechanicInvalidException('Mechanic patch field is invalid');
        }

        return $value;
    }
}
