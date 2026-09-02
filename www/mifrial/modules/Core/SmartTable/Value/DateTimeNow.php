<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Value;

/**
 * Маркер default datetime: на add без ключа подставить DateTime::now().
 */
final class DateTimeNow
{
    private static ?self $instance = null;

    /**
     * Закрытый конструктор: один экземпляр.
     *
     * @return void
     */
    private function __construct()
    {
    }

    /**
     * Возвращает общий маркер.
     *
     * @return self Маркер.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }
}
