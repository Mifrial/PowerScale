<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;

/**
 * Порты листа, ревизии и слоёв ударного домена.
 */
final class GameStrikeCharacterPorts
{
    /**
     * Создаёт доступ к портам листа.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     */
    public function __construct(private readonly IServiceLocator $serviceLocator)
    {
    }

    /**
     * Срез правил.
     *
     * @return ICharacterRuleSlices Срез.
     *
     * @throws KernelException Если порта нет.
     */
    public function ruleSlices(): ICharacterRuleSlices
    {
        $ruleSlices = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacterRuleSlices::class);
        if (!$ruleSlices instanceof ICharacterRuleSlices) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterRuleSlices');
        }

        return $ruleSlices;
    }

    /**
     * Строки персонажа.
     *
     * @return ICharacters Фасад.
     *
     * @throws KernelException Если порта нет.
     */
    public function characters(): ICharacters
    {
        $characters = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacters::class);
        if (!$characters instanceof ICharacters) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacters');
        }

        return $characters;
    }

    /**
     * Контекст формулы из листа.
     *
     * @return ICharacterFormulaContexts Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function formulaContexts(): ICharacterFormulaContexts
    {
        $contexts = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacterFormulaContexts::class);
        if (!$contexts instanceof ICharacterFormulaContexts) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterFormulaContexts');
        }

        return $contexts;
    }

    /**
     * Полный пересчёт листа.
     *
     * @return ICharacterSheets Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function characterSheets(): ICharacterSheets
    {
        $sheets = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacterSheets::class);
        if (!$sheets instanceof ICharacterSheets) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterSheets');
        }

        return $sheets;
    }

    /**
     * Проекция слоёв.
     *
     * @return ICharacterCombatLayers Порт.
     *
     * @throws KernelException Если порта нет.
     */
    public function combatLayers(): ICharacterCombatLayers
    {
        $layers = $this->serviceLocator->get(ICharacterContainer::class)->get(ICharacterCombatLayers::class);
        if (!$layers instanceof ICharacterCombatLayers) {
            throw new KernelException('PORT_TYPE', 'Game strike requires ICharacterCombatLayers');
        }

        return $layers;
    }
}
