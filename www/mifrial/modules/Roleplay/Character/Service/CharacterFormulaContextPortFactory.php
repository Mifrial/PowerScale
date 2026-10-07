<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;

/**
 * Сборка контекста формулы из локатора.
 */
final class CharacterFormulaContextPortFactory
{
    /**
     * Создаёт порт.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterFormulaContexts Порт.
     *
     * @throws KernelException Если нет фасада персонажа.
     */
    public function create(IServiceLocator $serviceLocator): ICharacterFormulaContexts
    {
        return new CharacterFormulaContexts((new CharacterPortFactory())->create($serviceLocator));
    }
}
