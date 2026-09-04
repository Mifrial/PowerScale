<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Repository;

use Closure;
use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `mail_event`.
 */
final class MailEventRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $eventRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $eventRecords,
    ) {
    }

    /**
     * Событие по коду.
     *
     * @param string $code Ключ.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function findByCode(string $code): ?array
    {
        return $this->eventRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['code' => $code],
            'limit' => 1,
        ]));
    }

    /**
     * Пишет событие.
     *
     * @param string $code Ключ.
     * @param string $name Имя.
     *
     * @return int Id.
     */
    public function add(string $code, string $name): int
    {
        return $this->write(function () use ($code, $name): int {
            return $this->eventRecords->add([
                'code' => $code,
                'name' => $name,
            ]);
        });
    }

    /**
     * Мапит ошибки строки.
     *
     * @param Closure $work Запись.
     *
     * @return mixed Результат.
     *
     * @throws MailException Если поля или unique.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail event code is duplicate', $exception);
        } catch (RowNotFoundException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail event row is missing', $exception);
        } catch (FieldRequiredException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail event field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail event field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail event map is invalid', $exception);
        }
    }
}
