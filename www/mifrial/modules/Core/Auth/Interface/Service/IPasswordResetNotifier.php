<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Interface\Service;

/**
 * Доставка сырого reset-токена.
 */
interface IPasswordResetNotifier
{
    /**
     * Ставит доставку без отправки наружу.
     *
     * @param string $login Логин учётки.
     * @param string $rawToken Сырой токен.
     * @param string $email Почта.
     *
     * @return int Id job или 0, если очереди нет.
     */
    public function enqueue(string $login, string $rawToken, string $email): int;

    /**
     * Отправляет уже поставленную доставку.
     *
     * @param int $jobId Id job.
     *
     * @return void
     */
    public function deliver(int $jobId): void;

    /**
     * Нужно ли отдать сырой токен в JSON (dev).
     *
     * @return bool true, если expose.
     */
    public function shouldExposeRawToken(): bool;
}
