<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Repository;

use Closure;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `mail_job`.
 */
final class MailJobRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $jobRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $jobRecords,
    ) {
    }

    /**
     * Job по id.
     *
     * @param int $jobId Id.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function getById(int $jobId): ?array
    {
        return $this->jobRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['id' => $jobId],
            'limit' => 1,
        ]));
    }

    /**
     * Страница pending.
     *
     * @param int $offset Сдвиг.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function listPending(int $offset): array
    {
        return $this->jobRecords->getList(ListQuery::fromOptions([
            'filter' => ['status' => 'pending'],
            'sort' => ['created_at' => 'asc', 'id' => 'asc'],
            'limit' => 500,
            'offset' => $offset,
        ]))->rows();
    }

    /**
     * Пишет pending job.
     *
     * @param int $eventId Событие.
     * @param array<string, string> $payload Поля.
     *
     * @return int Id.
     */
    public function addPending(int $eventId, array $payload): int
    {
        return $this->write(function () use ($eventId, $payload): int {
            return $this->jobRecords->add([
                'event_id' => $eventId,
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
            ]);
        });
    }

    /**
     * Помечает sent.
     *
     * @param int $jobId Id.
     * @param int $attempts Попытки.
     *
     * @return void
     */
    public function markSent(int $jobId, int $attempts): void
    {
        $this->write(function () use ($jobId, $attempts): mixed {
            $this->jobRecords->update($jobId, [
                'status' => 'sent',
                'sent_at' => DateTime::now(),
                'attempts' => $attempts,
                'last_error' => null,
            ]);

            return null;
        });
    }

    /**
     * Помечает failed.
     *
     * @param int $jobId Id.
     * @param int $attempts Попытки.
     * @param string $lastError Текст.
     *
     * @return void
     */
    public function markFailed(int $jobId, int $attempts, string $lastError): void
    {
        $this->write(function () use ($jobId, $attempts, $lastError): mixed {
            $this->jobRecords->update($jobId, [
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => $lastError,
            ]);

            return null;
        });
    }

    /**
     * Мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws MailException Если поля.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail job is duplicate', $exception);
        } catch (RowNotFoundException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail job row is missing', $exception);
        } catch (FieldRequiredException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail job field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail job field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail job map is invalid', $exception);
        }
    }
}
