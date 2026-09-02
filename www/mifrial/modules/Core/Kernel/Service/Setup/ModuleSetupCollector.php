<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service\Setup;

use Closure;
use Mifrial\Core\Kernel\Exception\Setup\SetupException;
use Mifrial\Core\Kernel\Interface\Service\IModuleManager;
use Mifrial\Core\Kernel\Interface\Service\IModuleSetup;
use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;

/**
 * Читает ключ setup из конфигов загруженных модулей.
 */
final class ModuleSetupCollector
{
    /**
     * Собирает setup модулей, у которых ключ объявлен.
     *
     * @param IModuleManager $moduleManager Загруженные модули.
     * @param IServiceLocator $serviceLocator Локатор для closure setup.
     *
     * @return array<int, array{key: string, setup: IModuleSetup}> Записи.
     *
     * @throws SetupException Если ключ setup некорректен.
     */
    public function collect(IModuleManager $moduleManager, IServiceLocator $serviceLocator): array
    {
        $entries = [];
        foreach ($moduleManager->getLoadedModules() as $loadedModule) {
            $moduleKey = $loadedModule['group'] . '/' . $loadedModule['name'];
            $setup = $this->resolveSetup($loadedModule['config']['setup'] ?? null, $moduleKey, $serviceLocator);
            if ($setup === null) {
                continue;
            }

            $entries[] = [
                'key' => $moduleKey,
                'setup' => $setup,
            ];
        }

        return $entries;
    }

    /**
     * Разворачивает class-string или closure локатора.
     *
     * @param mixed $setupConfig Значение ключа setup.
     * @param string $moduleKey Group/Name.
     * @param IServiceLocator $serviceLocator Локатор.
     *
     * @return IModuleSetup|null Setup или null, если ключа нет.
     *
     * @throws SetupException Если значение недопустимо.
     */
    private function resolveSetup(
        mixed $setupConfig,
        string $moduleKey,
        IServiceLocator $serviceLocator,
    ): ?IModuleSetup {
        if ($setupConfig === null) {
            return null;
        }

        if (is_string($setupConfig)) {
            return $this->fromClass($setupConfig, $moduleKey);
        }

        if ($setupConfig instanceof Closure) {
            return $this->fromClosure($setupConfig, $moduleKey, $serviceLocator);
        }

        throw new SetupException(
            'SETUP_INVALID',
            'Module setup must be a class or locator closure: ' . $moduleKey,
        );
    }

    /**
     * Создаёт setup без аргументов.
     *
     * @param string $setupClass Класс.
     * @param string $moduleKey Group/Name.
     *
     * @return IModuleSetup Setup.
     *
     * @throws SetupException Если класс не IModuleSetup.
     */
    private function fromClass(string $setupClass, string $moduleKey): IModuleSetup
    {
        if (!is_a($setupClass, IModuleSetup::class, true)) {
            throw new SetupException(
                'SETUP_INVALID',
                'Module setup class is invalid: ' . $moduleKey,
            );
        }

        $setup = new $setupClass();
        if (!$setup instanceof IModuleSetup) {
            throw new SetupException(
                'SETUP_INVALID',
                'Module setup class is invalid: ' . $moduleKey,
            );
        }

        return $setup;
    }

    /**
     * Вызывает closure setup.
     *
     * @param Closure $setupFactory Фабрика.
     * @param string $moduleKey Group/Name.
     * @param IServiceLocator $serviceLocator Локатор.
     *
     * @return IModuleSetup Setup.
     *
     * @throws SetupException Если closure вернула не IModuleSetup.
     */
    private function fromClosure(
        Closure $setupFactory,
        string $moduleKey,
        IServiceLocator $serviceLocator,
    ): IModuleSetup {
        $setup = $setupFactory($serviceLocator);
        if (!$setup instanceof IModuleSetup) {
            throw new SetupException(
                'SETUP_INVALID',
                'Module setup closure is invalid: ' . $moduleKey,
            );
        }

        return $setup;
    }
}
