<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

/**
 * Пишет письмо в error_log.
 */
final class LogMailTransport implements IMailTransport
{
    /**
     * Логирует from/to/subject.
     *
     * @param string $emailFrom От кого.
     * @param string $emailTo Кому.
     * @param string $subject Тема.
     * @param string $body Тело.
     *
     * @return void
     */
    public function send(string $emailFrom, string $emailTo, string $subject, string $body): void
    {
        error_log('mail.send from=' . $emailFrom . ' to=' . $emailTo . ' subject=' . $subject);
        error_log('mail.body ' . $body);
    }
}
