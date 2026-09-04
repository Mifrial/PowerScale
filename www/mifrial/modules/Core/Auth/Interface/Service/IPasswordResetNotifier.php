<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Interface\Service;

/**
 * Доставка сырого reset-токена.
 */
interface IPasswordResetNotifier
{
    /**
     * Сообщает логин, сырой токен и почту.
     *
     * @param string $login Логин учётки.
     * @param string $rawToken Сырой токен.
     * @param string $email Почта.
     *
     * @return void
     */
    public function notify(string $login, string $rawToken, string $email): void;


    /**
     * Нужно ли отдать сырой токен в JSON (dev).
     *
     * @return bool true, если expose.
     */
    public function shouldExposeRawToken(): bool;
}
