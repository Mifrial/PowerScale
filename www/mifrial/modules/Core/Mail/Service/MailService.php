<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

use Mifrial\Core\Mail\Dto\MailSettings;
use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\Mail\Interface\Service\IMail;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailJobRepository;

/**
 * Фасад очереди: только trigger.
 */
final class MailService implements IMail
{
    /**
     * Создаёт фасад.
     *
     * @param MailEventRepository $eventRepository События.
     * @param MailJobRepository $jobRepository Очередь.
     * @param MailSettings $mailSettings Срез mail.
     * @param MailFlushService $mailFlushService Flush.
     *
     * @return void
     */
    public function __construct(
        private readonly MailEventRepository $eventRepository,
        private readonly MailJobRepository $jobRepository,
        private readonly MailSettings $mailSettings,
        private readonly MailFlushService $mailFlushService,
    ) {
    }

    /**
     * Ставит job; при inline сразу flushJob.
     *
     * @param string $eventCode Код события.
     * @param array<string, mixed> $payload Поля.
     *
     * @return void
     *
     * @throws MailException Если код или payload недопустимы.
     */
    public function trigger(string $eventCode, array $payload): void
    {
        $code = trim($eventCode);
        $eventRow = $code === '' ? null : $this->eventRepository->findByCode($code);
        if ($eventRow === null) {
            throw new MailException('MAIL_INVALID', 'Mail event is unknown');
        }

        $jobId = $this->jobRepository->addPending((int) $eventRow['id'], $this->stringifyPayload($payload));
        if ($this->mailSettings->flushInline()) {
            $this->mailFlushService->flushJob($jobId);
        }
    }

    /**
     * Скаляры в строки.
     *
     * @param array<string, mixed> $payload Вход.
     *
     * @return array<string, string> Карта.
     *
     * @throws MailException Если ключ или значение недопустимы.
     */
    private function stringifyPayload(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $fieldName => $fieldValue) {
            $normalized[$this->payloadKey($fieldName)] = $this->payloadString($fieldValue);
        }

        return $normalized;
    }

    /**
     * Ключ payload.
     *
     * @param mixed $fieldName Ключ.
     *
     * @return string Имя.
     *
     * @throws MailException Если не строка.
     */
    private function payloadKey(mixed $fieldName): string
    {
        if (!is_string($fieldName) || $fieldName === '') {
            throw new MailException('MAIL_INVALID', 'Mail payload key is invalid');
        }

        return $fieldName;
    }

    /**
     * Значение payload.
     *
     * @param mixed $fieldValue Значение.
     *
     * @return string Строка.
     *
     * @throws MailException Если не скаляр.
     */
    private function payloadString(mixed $fieldValue): string
    {
        if (is_array($fieldValue) || is_object($fieldValue) || $fieldValue === null) {
            throw new MailException('MAIL_INVALID', 'Mail payload value is invalid');
        }

        if (!is_scalar($fieldValue)) {
            throw new MailException('MAIL_INVALID', 'Mail payload value is invalid');
        }

        return (string) $fieldValue;
    }
}
