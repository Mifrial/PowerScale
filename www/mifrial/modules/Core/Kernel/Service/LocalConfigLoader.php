<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

/**
 * Чтение config/{name}.php без побочных эффектов boot.
 */
final class LocalConfigLoader
{
    /**
     * Читает конфиг из корня Mifrial.
     *
     * Имя файла: `config/{MIFRIAL_CONFIG}.php`, по умолчанию `local.php`.
     * В phpunit задаётся `test` → `config/test.php`, не боевой local.php.
     *
     * @param string $root Корень Mifrial.
     *
     * @return array<string, mixed> Локальная конфигурация.
     */
    public function load(string $root): array
    {
        return $this->loadFromFile($root . '/config/' . $this->resolveConfigFileName());
    }

    /**
     * Читает конфиг по пути к файлу.
     *
     * @param string $configPath Путь к PHP-конфигу.
     *
     * @return array<string, mixed> Локальная конфигурация.
     */
    public function loadFromFile(string $configPath): array
    {
        $config = is_file($configPath) ? require $configPath : [];
        if (!is_array($config)) {
            return ['debug' => false];
        }

        if (!array_key_exists('debug', $config)) {
            $config['debug'] = false;
        }

        return $config;
    }

    /**
     * Имя файла конфига в каталоге config/.
     *
     * @return string Имя вроде local.php.
     */
    private function resolveConfigFileName(): string
    {
        $configName = getenv('MIFRIAL_CONFIG');
        if (!is_string($configName) || $configName === '') {
            return 'local.php';
        }

        if (preg_match('/^[a-z][a-z0-9_]*$/', $configName) !== 1) {
            return 'local.php';
        }

        return $configName . '.php';
    }
}
