<?php

declare(strict_types=1);

namespace Mifrial\Core\Logger\Service;

use Mifrial\Core\Logger\Dto\Action\FindLogPageInput;
use Mifrial\Core\Logger\Exception\LoggerInvalidException;
use Mifrial\Core\Logger\Exception\LoggerNotFoundException;
use Mifrial\Core\Logger\Repository\LogRepository;
use Mifrial\Core\User\Interface\Service\IUserAccess;

/**
 * HTTP-сценарии просмотра журнала: актор, страница, JSON.
 */
final class LoggerHttpService
{
    /**
     * Создаёт сценарий.
     *
     * @param IUserAccess $userAccess Guard.
     * @param LogRepository $logRepository Строки.
     * @param LogFindPageParser $logFindPageParser Разбор входа.
     * @param LogViewAssembler $logViewAssembler JSON.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly LogRepository $logRepository,
        private readonly LogFindPageParser $logFindPageParser,
        private readonly LogViewAssembler $logViewAssembler,
    ) {
    }

    /**
     * Страница журнала.
     *
     * @param FindLogPageInput $input JSON.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int} Страница.
     *
     * @throws LoggerInvalidException Если фильтр недопустим.
     */
    public function findPage(FindLogPageInput $input): array
    {
        $this->userAccess->requireKey(LoggerPermissionKeys::VIEW);
        $page = $this->logRepository->findPage($this->logFindPageParser->parse($input));
        $items = [];
        foreach ($page->getRecords() as $logRecord) {
            $items[] = $this->logViewAssembler->assemble($logRecord);
        }

        return [
            'items' => $items,
            'total' => $page->getTotal(),
        ];
    }

    /**
     * Одна запись.
     *
     * @param int $id Идентификатор.
     *
     * @return array<string, mixed> LogEntry.
     *
     * @throws LoggerNotFoundException Если строки нет.
     */
    public function get(int $id): array
    {
        $this->userAccess->requireKey(LoggerPermissionKeys::VIEW);

        return $this->logViewAssembler->assemble($this->logRepository->getById($id));
    }
}
