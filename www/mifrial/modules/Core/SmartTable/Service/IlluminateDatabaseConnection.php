<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service;

use Illuminate\Database\MySqlConnection;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConnectFailedException;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Throwable;

/**
 * Ленивый адаптер MySQL с проверкой ping.
 */
final class IlluminateDatabaseConnection implements IDatabaseConnection
{
    private ?MySqlConnection $mySqlConnection = null;

    /**
     * Создаёт ленивый адаптер.
     *
     * @param IlluminateConnectionFactory $connectionFactory Фабрика Illuminate-соединения.
     * @param DatabaseSettings $databaseSettings Настройки из Kernel.
     *
     * @return void
     */
    public function __construct(
        private readonly IlluminateConnectionFactory $connectionFactory,
        private readonly DatabaseSettings $databaseSettings,
    ) {
    }

    /**
     * Проверяет, что соединение живое.
     *
     * @return void
     *
     * @throws DatabaseException Если конфиг или сервер недоступны.
     */
    public function ping(): void
    {
        $this->illuminateConnection()->select('select 1 as ping');
    }

    /**
     * Возвращает внутреннее Illuminate-соединение.
     *
     * @return MySqlConnection Живое соединение.
     *
     * @throws DatabaseException Если открыть соединение нельзя.
     */
    public function illuminateConnection(): MySqlConnection
    {
        if ($this->mySqlConnection instanceof MySqlConnection) {
            return $this->mySqlConnection;
        }

        try {
            $this->mySqlConnection = $this->connectionFactory->open($this->databaseSettings);
        } catch (DatabaseException $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DbConnectFailedException($throwable);
        }

        return $this->mySqlConnection;
    }
}
