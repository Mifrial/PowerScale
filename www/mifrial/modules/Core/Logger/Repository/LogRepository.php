<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Repository;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Logger\Dto\LogPageQuery;
use Mifrial\Core\Logger\Dto\LogRecord;
use Mifrial\Core\Logger\Dto\LogRecordPage;
use Mifrial\Core\Logger\Exception\LoggerInvalidException;
use Mifrial\Core\Logger\Exception\LoggerNotFoundException;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Dto\ListResult;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `log`.
 */
final class LogRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $logRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $logRecords,
    ) {
    }

    /**
     * Добавляет запись.
     *
     * @param array<string, mixed> $fields Поля строки.
     *
     * @return int Id.
     */
    public function add(array $fields): int
    {
        return $this->logRecords->add($fields);
    }

    /**
     * Страница журнала: created_at desc, id desc, COUNT фильтра.
     *
     * @param LogPageQuery $pageQuery Запрос.
     *
     * @return LogRecordPage Страница.
     *
     * @throws LoggerInvalidException Если запрос ST недопустим.
     */
    public function findPage(LogPageQuery $pageQuery): LogRecordPage
    {
        $listResult = $this->readList(ListQuery::fromOptions([
            'limit' => $pageQuery->getLimit(),
            'offset' => $pageQuery->getOffset(),
            'countTotal' => true,
            'sort' => ['created_at' => 'desc', 'id' => 'desc'],
            'filter' => $this->pageFilter($pageQuery),
        ]));
        $records = [];
        foreach ($listResult->rows() as $row) {
            $records[] = LogRecord::fromNormalized($row);
        }

        return new LogRecordPage($records, $listResult->total() ?? 0);
    }

    /**
     * Одна запись.
     *
     * @param int $logId Идентификатор.
     *
     * @return LogRecord Запись.
     *
     * @throws LoggerNotFoundException Если строки нет.
     * @throws LoggerInvalidException Если гидрат неполный.
     */
    public function getById(int $logId): LogRecord
    {
        $row = $this->logRecords->getById($logId);
        if ($row === null) {
            throw new LoggerNotFoundException();
        }

        return LogRecord::fromNormalized($row);
    }

    /**
     * Последние строки для тестов записи.
     *
     * @param int $limit Лимит.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function listRecent(int $limit): array
    {
        return $this->logRecords->getList(ListQuery::fromOptions([
            'sort' => ['id' => 'desc'],
            'limit' => $limit,
        ]))->rows();
    }

    /**
     * Дерево фильтра страницы.
     *
     * @param LogPageQuery $pageQuery Запрос.
     *
     * @return array<string|int, mixed>|null WHERE.
     */
    private function pageFilter(LogPageQuery $pageQuery): ?array
    {
        $clauses = [];
        $this->pushEquals($clauses, 'level', $pageQuery->getLevel());
        $this->pushText($clauses, 'source', $pageQuery->getSource(), $pageQuery->isSourceContains());
        $this->pushText($clauses, 'error_code', $pageQuery->getErrorCode(), $pageQuery->isErrorCodeContains());
        $this->pushCreatedAt($clauses, $pageQuery->getFromUnix(), $pageQuery->getToUnix());
        if ($clauses === []) {
            return null;
        }

        if (count($clauses) === 1) {
            return $clauses[0];
        }

        $andGroup = ['LOGIC' => 'AND'];
        foreach ($clauses as $clause) {
            $andGroup[] = $clause;
        }

        return $andGroup;
    }

    /**
     * Добавляет equals, если значение есть.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     * @param string $fieldName Колонка.
     * @param string|null $value Значение.
     *
     * @return void
     */
    private function pushEquals(array &$clauses, string $fieldName, ?string $value): void
    {
        if ($value !== null) {
            $clauses[] = ['=' . $fieldName => $value];
        }
    }

    /**
     * Добавляет equals или LIKE.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     * @param string $fieldName Колонка.
     * @param string|null $value Текст.
     * @param bool $contains LIKE.
     *
     * @return void
     */
    private function pushText(array &$clauses, string $fieldName, ?string $value, bool $contains): void
    {
        if ($value === null) {
            return;
        }

        if ($contains) {
            $clauses[] = ['%' . $fieldName => '%' . addcslashes($value, '%_\\') . '%'];

            return;
        }

        $clauses[] = ['=' . $fieldName => $value];
    }

    /**
     * Границы created_at как DateTime ST.
     *
     * @param array<int, array<string, mixed>> $clauses Условия.
     * @param int|null $fromUnix Нижняя граница.
     * @param int|null $toUnix Верхняя граница.
     *
     * @return void
     */
    private function pushCreatedAt(array &$clauses, ?int $fromUnix, ?int $toUnix): void
    {
        if ($fromUnix !== null) {
            $clauses[] = ['>=created_at' => DateTime::fromUnix($fromUnix)];
        }

        if ($toUnix !== null) {
            $clauses[] = ['<=created_at' => DateTime::fromUnix($toUnix)];
        }
    }

    /**
     * Читает страницу через SmartTable.
     *
     * @param ListQuery $listQuery Запрос.
     *
     * @return ListResult Страница.
     *
     * @throws LoggerInvalidException Если запрос недопустим.
     */
    private function readList(ListQuery $listQuery): ListResult
    {
        try {
            return $this->logRecords->getList($listQuery);
        } catch (MapInvalidException $exception) {
            throw new LoggerInvalidException('Log list query is invalid', $exception);
        }
    }
}
