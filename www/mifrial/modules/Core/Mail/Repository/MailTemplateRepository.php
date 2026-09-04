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
 * Строки `mail_template`.
 */
final class MailTemplateRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $templateRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $templateRecords,
    ) {
    }

    /**
     * Шаблон события по имени.
     *
     * @param int $eventId Событие.
     * @param string $name Имя шаблона.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function findByEventIdAndName(int $eventId, string $name): ?array
    {
        return $this->templateRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['event_id' => $eventId, 'name' => $name],
            'limit' => 1,
        ]));
    }

    /**
     * Активные шаблоны события.
     *
     * @param int $eventId Событие.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function listActiveByEventId(int $eventId): array
    {
        return $this->templateRecords->getList(ListQuery::fromOptions([
            'filter' => ['event_id' => $eventId, 'active' => true],
            'sort' => ['id' => 'asc'],
            'limit' => 500,
        ]))->rows();
    }

    /**
     * Пишет шаблон.
     *
     * @param array<string, mixed> $values Поля.
     *
     * @return int Id.
     */
    public function add(array $values): int
    {
        return $this->write(function () use ($values): int {
            return $this->templateRecords->add($values);
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
            throw new MailException('MAIL_INVALID', 'Mail template is duplicate', $exception);
        } catch (RowNotFoundException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail template row is missing', $exception);
        } catch (FieldRequiredException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail template field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail template field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new MailException('MAIL_INVALID', 'Mail template map is invalid', $exception);
        }
    }
}
