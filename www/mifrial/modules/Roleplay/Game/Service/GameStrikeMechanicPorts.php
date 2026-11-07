<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;

/**
 * Порты механик и формул ударного домена.
 */
final class GameStrikeMechanicPorts
{
    /**
     * Создаёт доступ к портам механик.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     */
    public function __construct(private readonly IServiceLocator $serviceLocator)
    {
    }

    /**
     * Движок механик.
     *
     * @return IMechanicEngine Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function engine(): IMechanicEngine
    {
        $engine = $this->serviceLocator->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        if (!$engine instanceof IMechanicEngine) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanicEngine');
        }

        return $engine;
    }

    /**
     * Каталог механик.
     *
     * @return IMechanics Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function mechanics(): IMechanics
    {
        $mechanics = $this->serviceLocator->get(IMechanicContainer::class)->get(IMechanics::class);
        if (!$mechanics instanceof IMechanics) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanics');
        }

        return $mechanics;
    }

    /**
     * Порт броска.
     *
     * @return IMechanicRolls Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function rolls(): IMechanicRolls
    {
        $rolls = $this->serviceLocator->get(IMechanicContainer::class)->get(IMechanicRolls::class);
        if (!$rolls instanceof IMechanicRolls) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IMechanicRolls');
        }

        return $rolls;
    }

    /**
     * Обход формулы.
     *
     * @return IFormulaEvaluations Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function formulaEvaluations(): IFormulaEvaluations
    {
        $evaluations = $this->serviceLocator->get(IRuleContainer::class)->get(IFormulaEvaluations::class);
        if (!$evaluations instanceof IFormulaEvaluations) {
            throw new KernelException('PORT_TYPE', 'Game strike requires IFormulaEvaluations');
        }

        return $evaluations;
    }
}
