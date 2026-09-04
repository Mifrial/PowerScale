<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Dto;

use Mifrial\Core\Kernel\Exception\Setup\SetupException;

/**
 * Срез `auth` из local.php.
 */
final class AuthSettings
{
    /**
     * Собирает настройки.
     *
     * @param string $operatorLogin Логин seed-оператора.
     * @param string $operatorPassword Пароль seed-оператора.
     * @param string $operatorName Имя seed-оператора.
     * @param bool $cookieSecure Secure на cookie.
     * @param bool $exposeResetToken Отдавать сырой reset-токен в JSON.
     *
     * @return void
     */
    private function __construct(
        private readonly string $operatorLogin,
        private readonly string $operatorPassword,
        private readonly string $operatorName,
        private readonly bool $cookieSecure,
        private readonly bool $exposeResetToken,
    ) {
    }

    /**
     * Разбирает срез конфига; пустой массив — только cookie_secure=false.
     *
     * @param mixed $section Значение ключа auth.
     *
     * @return self Настройки.
     *
     * @throws SetupException Если срез не массив.
     */
    public static function fromSection(mixed $section): self
    {
        if ($section === null) {
            $section = [];
        }

        if (!is_array($section)) {
            throw new SetupException('SETUP_INVALID', 'auth config must be an array');
        }

        return new self(
            self::stringField($section, 'operator_login'),
            self::stringField($section, 'operator_password'),
            self::stringField($section, 'operator_name'),
            ($section['cookie_secure'] ?? false) === true,
            ($section['expose_reset_token'] ?? false) === true,
        );
    }

    /**
     * Логин оператора.
     *
     * @return string Логин.
     */
    public function operatorLogin(): string
    {
        return $this->operatorLogin;
    }

    /**
     * Пароль оператора.
     *
     * @return string Пароль.
     */
    public function operatorPassword(): string
    {
        return $this->operatorPassword;
    }

    /**
     * Имя оператора.
     *
     * @return string Имя.
     */
    public function operatorName(): string
    {
        return $this->operatorName;
    }

    /**
     * Признак Secure у cookie.
     *
     * @return bool true, если HTTPS.
     */
    public function cookieSecure(): bool
    {
        return $this->cookieSecure;
    }

    /**
     * Dev: сырой токен в ответе startPasswordReset.
     *
     * @return bool true, если отдавать.
     */
    public function exposeResetToken(): bool
    {
        return $this->exposeResetToken;
    }

    /**
     * Требует непустые поля оператора для seed.
     *
     * @return void
     *
     * @throws SetupException Если поле пусто.
     */
    public function assertOperatorComplete(): void
    {
        if ($this->operatorLogin === '' || $this->operatorPassword === '' || $this->operatorName === '') {
            throw new SetupException('SETUP_INVALID', 'auth operator settings are incomplete');
        }
    }

    /**
     * Читает строковое поле.
     *
     * @param array<mixed> $section Срез.
     * @param string $fieldName Ключ.
     *
     * @return string Значение или пустая строка.
     */
    private static function stringField(array $section, string $fieldName): string
    {
        $fieldValue = $section[$fieldName] ?? '';

        return is_string($fieldValue) ? trim($fieldValue) : '';
    }
}
