<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterOsSteps;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;

/**
 * Сборка шага «Основа» из локатора.
 */
final class CharacterOsStepsPortFactory
{
    /**
     * Создаёт шаг.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterOsSteps Порт.
     *
     * @throws KernelException Если нет движка или каталога механик.
     */
    public function create(IServiceLocator $serviceLocator): ICharacterOsSteps
    {
        return new CharacterOsSteps(
            $this->engine($serviceLocator),
            $this->mechanics($serviceLocator),
        );
    }

    /**
     * Движок Mechanic.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IMechanicEngine Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function engine(IServiceLocator $serviceLocator): IMechanicEngine
    {
        $engine = $serviceLocator->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        if (!$engine instanceof IMechanicEngine) {
            throw new KernelException('PORT_TYPE', 'Character requires IMechanicEngine');
        }

        return $engine;
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
