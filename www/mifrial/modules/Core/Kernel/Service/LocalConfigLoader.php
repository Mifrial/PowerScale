<?php

declare(strict_types=1);

namespace Mifrial\Core\Kernel\Service;

/**
 * Чтение config/local.php без побочных эффектов boot.
 */
final class LocalConfigLoader
{
    /**
     * Читает конфиг из корня Mifrial.
     *
     * @param string $root Корень Mifrial.
     *
     * @return array<string, mixed> Локальная конфигурация.
     */
    public function load(string $root): array
    {
        return $this->loadFromFile($root . '/config/local.php');
    }

    /**
     * Читает конфиг по пути к файлу.
     *
     * @param string $configPath Путь к local.php.
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
}
