<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Exception\Database;

/**
 * Непригодный срез db из RuntimeConfig.
 */
final class DbConfigInvalidException extends DatabaseException
{
    /**
     * Создаёт ошибку конфигурации соединения.
     *
     * @param string $message Текст без пароля и DSN.
     *
     * @return void
     */
    public function __construct(string $message = 'MySQL settings are invalid')
    {
        parent::__construct('DB_CONFIG_INVALID', $message);
    }
}
