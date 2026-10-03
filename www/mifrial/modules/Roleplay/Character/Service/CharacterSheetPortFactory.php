<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Сборка валидатора листа из локатора.
 */
final class CharacterSheetPortFactory
{
    /**
     * Создаёт валидатор.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterSheets Порт.
     *
     * @throws KernelException Если нет шага доплаты или каталога механик.
     */
    public function create(IServiceLocator $serviceLocator): ICharacterSheets
    {
        return new CharacterSheets(
            $this->osSteps($serviceLocator),
            $this->mechanics($serviceLocator),
        );
    }

    /**
     * Шаг доплаты.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterOsSteps Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function osSteps(IServiceLocator $serviceLocator): ICharacterOsSteps
    {
        $osSteps = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterOsSteps::class);
        if (!$osSteps instanceof ICharacterOsSteps) {
            throw new KernelException('PORT_TYPE', 'Character requires ICharacterOsSteps');
        }

        return $osSteps;
    }

    /**
     * Каталог механик.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanics Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function mechanics(IServiceLocator $serviceLocator): IMechanics
    {
        $mechanics = $serviceLocator->get(IMechanicContainer::class)->get(IMechanics::class);
        if (!$mechanics instanceof IMechanics) {
            throw new KernelException('PORT_TYPE', 'Character requires IMechanics');
        }

        return $mechanics;
    }
}
