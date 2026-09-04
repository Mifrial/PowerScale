<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Service;

use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\Mail\Repository\MailJobRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;
use Throwable;

/**
 * Сброс pending jobs на транспорт.
 */
final class MailFlushService
{
    /**
     * Создаёт flush.
     *
     * @param MailJobRepository $jobRepository Очередь.
     * @param MailTemplateRepository $templateRepository Шаблоны.
     * @param PlaceholderRenderer $placeholderRenderer Подстановка.
     * @param IMailTransport $mailTransport Транспорт.
     *
     * @return void
     */
    public function __construct(
        private readonly MailJobRepository $jobRepository,
        private readonly MailTemplateRepository $templateRepository,
        private readonly PlaceholderRenderer $placeholderRenderer,
        private readonly IMailTransport $mailTransport,
    ) {
    }

    /**
     * Pending по created_at. Limit — сколько jobs максимум.
     *
     * @param int|null $limit Потолок или все.
     *
     * @return void
     */
    public function flush(?int $limit = null): void
    {
        $remaining = $limit;
        do {
            $pageRows = $this->jobRepository->listPending(0);
            $processed = $this->flushPage($pageRows, $remaining);
            if ($remaining !== null) {
                $remaining -= $processed;
            }
        } while (count($pageRows) === 500 && $processed > 0 && ($remaining === null || $remaining > 0));
    }

    /**
     * Один pending job.
     *
     * @param int $jobId Id.
     *
     * @return void
     */
    public function flushJob(int $jobId): void
    {
        $jobRow = $this->jobRepository->getById($jobId);
        if ($jobRow === null || (string) $jobRow['status'] !== 'pending') {
            return;
        }

        $this->flushLoadedJob($jobRow);
    }

    /**
     * Страница pending.
     *
     * @param array<int, array<string, mixed>> $pageRows Строки.
     * @param int|null $remaining Остаток лимита.
     *
     * @return int Сколько обработано.
     */
    private function flushPage(array $pageRows, ?int $remaining): int
    {
        $processed = 0;
        foreach ($pageRows as $jobRow) {
            if ($remaining !== null && $processed >= $remaining) {
                break;
            }

            $this->flushLoadedJob($jobRow);
            $processed++;
        }

        return $processed;
    }

    /**
     * Рендер и отправка шаблонов job.
     *
     * @param array<string, mixed> $jobRow Строка.
     *
     * @return void
     */
    private function flushLoadedJob(array $jobRow): void
    {
        $jobId = (int) $jobRow['id'];
        $attempts = ((int) $jobRow['attempts']) + 1;
        $templates = $this->templateRepository->listActiveByEventId((int) $jobRow['event_id']);
        if ($templates === []) {
            $this->jobRepository->markFailed($jobId, $attempts, 'No active mail templates');

            return;
        }

        $sendError = $this->sendTemplates($templates, $this->payloadMap($jobRow));
        if ($sendError === null) {
            $this->jobRepository->markSent($jobId, $attempts);

            return;
        }

        $this->jobRepository->markFailed($jobId, $attempts, $sendError);
    }

    /**
     * Все активные шаблоны. Уже ушедшие не отзываем.
     *
     * @param array<int, array<string, mixed>> $templates Шаблоны.
     * @param array<string, string> $payload Поля.
     *
     * @return string|null Первая ошибка или null.
     */
    private function sendTemplates(array $templates, array $payload): ?string
    {
        $sendError = null;
        foreach ($templates as $templateRow) {
            try {
                $this->sendOneTemplate($templateRow, $payload);
            } catch (Throwable $exception) {
                $sendError ??= $exception->getMessage();
            }
        }

        return $sendError;
    }

    /**
     * Один шаблон на транспорт.
     *
     * @param array<string, mixed> $templateRow Шаблон.
     * @param array<string, string> $payload Поля.
     *
     * @return void
     *
     * @throws MailException Если рендер или адрес недопустимы.
     */
    private function sendOneTemplate(array $templateRow, array $payload): void
    {
        $this->mailTransport->send(
            $this->renderAddress((string) $templateRow['email_from'], $payload),
            $this->renderAddress((string) $templateRow['email_to'], $payload),
            $this->placeholderRenderer->render((string) $templateRow['subject'], $payload),
            $this->placeholderRenderer->render((string) $templateRow['body'], $payload),
        );
    }

    /**
     * From/to после подстановки.
     *
     * @param string $template Адрес-шаблон.
     * @param array<string, string> $payload Поля.
     *
     * @return string Адрес.
     *
     * @throws MailException Если пусто или CR/LF.
     */
    private function renderAddress(string $template, array $payload): string
    {
        $rendered = trim($this->placeholderRenderer->render($template, $payload));
        if ($rendered === '' || str_contains($rendered, "\r") || str_contains($rendered, "\n")) {
            throw new MailException('MAIL_INVALID', 'Mail address is invalid');
        }

        return $rendered;
    }

    /**
     * Payload из строки job.
     *
     * @param array<string, mixed> $jobRow Строка.
     *
     * @return array<string, string> Карта.
     */
    private function payloadMap(array $jobRow): array
    {
        $payload = $jobRow['payload'] ?? [];
        if (!is_array($payload)) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $fieldName => $fieldValue) {
            if (is_string($fieldName) && is_scalar($fieldValue)) {
                $normalized[$fieldName] = (string) $fieldValue;
            }
        }

        return $normalized;
    }
}
