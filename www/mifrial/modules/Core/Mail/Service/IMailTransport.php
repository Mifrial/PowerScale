<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

/**
 * Отправка уже собранного письма. Не порт соседа.
 */
interface IMailTransport
{
    /**
     * Отправляет письмо.
     *
     * @param string $emailFrom От кого.
     * @param string $emailTo Кому.
     * @param string $subject Тема.
     * @param string $body Тело.
     *
     * @return void
     */
    public function send(string $emailFrom, string $emailTo, string $subject, string $body): void;
}
