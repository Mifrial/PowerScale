<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;

/**
 * Сборка проекции слоёв из локатора.
 */
final class CharacterCombatLayerPortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterCombatLayers Порт.
     *
     * @throws KernelException Если порт соседа чужого типа.
     */
    public function create(IServiceLocator $serviceLocator): ICharacterCombatLayers
    {
        return new CharacterCombatLayers(
            $this->slices($serviceLocator),
            $this->contexts($serviceLocator),
            $this->evaluations($serviceLocator),
        );
    }

    /**
     * Срез правил.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterRuleSlices Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function slices(IServiceLocator $serviceLocator): ICharacterRuleSlices
    {
        $slices = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterRuleSlices::class);
        if (!$slices instanceof ICharacterRuleSlices) {
            throw new KernelException('PORT_TYPE', 'Character combat layers require ICharacterRuleSlices');
        }

        return $slices;
    }

    /**
     * Контекст формулы.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return ICharacterFormulaContexts Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function contexts(IServiceLocator $serviceLocator): ICharacterFormulaContexts
    {
        $contexts = $serviceLocator->get(ICharacterContainer::class)->get(ICharacterFormulaContexts::class);
        if (!$contexts instanceof ICharacterFormulaContexts) {
            throw new KernelException('PORT_TYPE', 'Character combat layers require ICharacterFormulaContexts');
        }

        return $contexts;
    }

    /**
     * Расчёт формул.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IFormulaEvaluations Порт.
     *
     * @throws KernelException Если тип чужой.
     */
    private function evaluations(IServiceLocator $serviceLocator): IFormulaEvaluations
    {
        $evaluations = $serviceLocator->get(IRuleContainer::class)->get(IFormulaEvaluations::class);
        if (!$evaluations instanceof IFormulaEvaluations) {
            throw new KernelException('PORT_TYPE', 'Character combat layers require IFormulaEvaluations');
        }

        return $evaluations;
    }
}
