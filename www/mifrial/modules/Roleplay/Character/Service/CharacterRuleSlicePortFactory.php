<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\KernelException;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Service\IRuleSpaces;

/**
 * Сборка загрузчика среза из локатора.
 */
final class CharacterRuleSlicePortFactory
{
    /**
     * Создаёт загрузчик.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return ICharacterRuleSlices Порт.
     *
     * @throws KernelException Если нет IRuleSpaces или IKeywords.
     */
    public function create(IServiceLocator $serviceLocator): ICharacterRuleSlices
    {
        return new CharacterRuleSlices(
            $this->ruleSpaces($serviceLocator),
            $this->keywords($serviceLocator),
        );
    }

    /**
     * Миры.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IRuleSpaces Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function ruleSpaces(IServiceLocator $serviceLocator): IRuleSpaces
    {
        $ruleSpaces = $serviceLocator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class);
        if (!$ruleSpaces instanceof IRuleSpaces) {
            throw new KernelException('PORT_TYPE', 'Character requires IRuleSpaces');
        }

        return $ruleSpaces;
    }

    /**
     * Признаки.
     *
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IKeywords Фасад.
     *
     * @throws KernelException Если тип чужой.
     */
    private function keywords(IServiceLocator $serviceLocator): IKeywords
    {
        $keywords = $serviceLocator->get(IKeywordContainer::class)->get(IKeywords::class);
        if (!$keywords instanceof IKeywords) {
            throw new KernelException('PORT_TYPE', 'Character requires IKeywords');
        }

        return $keywords;
    }
}
