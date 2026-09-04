<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Service;

use Mifrial\Core\Agent\Exception\AgentException;
use Mifrial\Core\Agent\Interface\Container\IAgentContainer;
use Mifrial\Core\Agent\Interface\Service\IAgentHandler;
use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Kernel\Interface\Container\IModuleContainer;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Биндинг обработчиков из ключа `agents` module.config. Только CLI.
 */
final class AgentTickCli
{
    /**
     * Вешает обработчики загруженных модулей и делает тик.
     *
     * @param array<int, array{group: string, name: string, config: array<string, mixed>}> $loadedModules Модули.
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     *
     * @return void
     *
     * @throws AgentException Если ключ agents кривой.
     */
    public function run(
        array $loadedModules,
        IServiceLocator $serviceLocator,
    ): void {
        $agentContainer = $serviceLocator->get(IAgentContainer::class);
        $agents = $agentContainer->get(IAgents::class);
        if (!$agents instanceof IAgents) {
            throw new AgentException('AGENT_INVALID', 'IAgents port is missing');
        }

        foreach ($loadedModules as $loadedModule) {
            $this->bindModule($loadedModule, $serviceLocator, $agents);
        }

        $agents->tick();
    }

    /**
     * Читает `agents` одного модуля.
     *
     * @param array{group: string, name: string, config: array<string, mixed>} $loadedModule Модуль.
     * @param IServiceLocator $serviceLocator Каталог.
     * @param IAgents $agents Фасад.
     *
     * @return void
     *
     * @throws AgentException Если карта agents неверна.
     */
    private function bindModule(
        array $loadedModule,
        IServiceLocator $serviceLocator,
        IAgents $agents,
    ): void {
        $agentsMap = $this->agentsMap($loadedModule['config']['agents'] ?? null);
        if ($agentsMap === []) {
            return;
        }

        $moduleContainer = $this->moduleContainer($loadedModule['config']['locator'] ?? null, $serviceLocator);
        foreach ($agentsMap as $code => $handlerClass) {
            $this->bindHandler($agents, $moduleContainer, $code, $handlerClass);
        }
    }

    /**
     * Нормализует ключ agents.
     *
     * @param mixed $agentsMap Сырое значение.
     *
     * @return array<string, string> code => class.
     *
     * @throws AgentException Если форма не карта.
     */
    private function agentsMap(mixed $agentsMap): array
    {
        if ($agentsMap === null || $agentsMap === []) {
            return [];
        }

        if (!is_array($agentsMap) || array_is_list($agentsMap)) {
            throw new AgentException('AGENT_INVALID', 'Module agents map is invalid');
        }

        return $agentsMap;
    }

    /**
     * Контейнер донора.
     *
     * @param mixed $locatorKey Ключ locator.
     * @param IServiceLocator $serviceLocator Каталог.
     *
     * @return IModuleContainer Контейнер.
     *
     * @throws AgentException Если locator нет.
     */
    private function moduleContainer(mixed $locatorKey, IServiceLocator $serviceLocator): IModuleContainer
    {
        if (!is_string($locatorKey) || $locatorKey === '') {
            throw new AgentException('AGENT_INVALID', 'Module locator is required for agents');
        }

        return $serviceLocator->get($locatorKey);
    }

    /**
     * Один обработчик.
     *
     * @param IAgents $agents Фасад.
     * @param IModuleContainer $moduleContainer Контейнер донора.
     * @param mixed $code Ключ агента.
     * @param mixed $handlerClass class-string.
     *
     * @return void
     *
     * @throws AgentException Если entry кривой.
     */
    private function bindHandler(
        IAgents $agents,
        IModuleContainer $moduleContainer,
        mixed $code,
        mixed $handlerClass,
    ): void {
        if (!is_string($code) || !is_string($handlerClass) || $handlerClass === '') {
            throw new AgentException('AGENT_INVALID', 'Agent handler map entry is invalid');
        }

        $handler = $moduleContainer->get($handlerClass);
        if (!$handler instanceof IAgentHandler) {
            throw new AgentException('AGENT_INVALID', 'Agent handler must implement IAgentHandler');
        }

        $agents->bindHandler($code, $handler);
    }
}
