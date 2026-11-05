<?php

declare(strict_types=1);

namespace Mifrial\Core\SmartTable\Service\Schema;

use RuntimeException;

/**
 * Защита от случайного DROP таблицы вне тестового контура.
 *
 * Маркер ставит `tests/bootstrap.php` после подключения к тестовой БД.
 * Осознанный ручной снос (обслуживание, миграция) разрешается переменной
 * окружения `MIFRIAL_ALLOW_DESTRUCTIVE_DDL=1`.
 */
final class DestructiveDdlGuard
{
    /**
     * Маркер процесса, прошедшего тестовый bootstrap.
     *
     * @var string
     */
    public const MARKER = 'MIFRIAL_TEST_DATABASE';

    /**
     * Переменная окружения для осознанного ручного DROP.
     *
     * @var string
     */
    public const ENV_ALLOW = 'MIFRIAL_ALLOW_DESTRUCTIVE_DDL';

    /**
     * Пропускает DROP только в тестах или при явном разрешении.
     *
     * @return void
     *
     * @throws RuntimeException Если DROP вызван вне тестового контура.
     */
    public static function assertAllowed(): void
    {
        if (defined(self::MARKER) || getenv(self::ENV_ALLOW) === '1') {
            return;
        }

        throw new RuntimeException(
            'Refusing to drop a table outside the test bootstrap. '
            . 'Run through phpunit or set ' . self::ENV_ALLOW . '=1 for a deliberate manual drop.',
        );
    }
}
