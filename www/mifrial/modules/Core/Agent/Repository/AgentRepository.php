<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Repository;

use Closure;
use Mifrial\Core\Agent\Exception\AgentException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Field\FieldInvalidException;
use Mifrial\Core\SmartTable\Exception\Field\FieldRequiredException;
use Mifrial\Core\SmartTable\Exception\Map\MapInvalidException;
use Mifrial\Core\SmartTable\Exception\Row\RowNotFoundException;
use Mifrial\Core\SmartTable\Exception\Row\UniqueConstraintException;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedRecords;

/**
 * Строки `agent`.
 */
final class AgentRepository
{
    /**
     * Создаёт репозиторий.
     *
     * @param IOpenedRecords $agentRecords Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly IOpenedRecords $agentRecords,
    ) {
    }

    /**
     * Строка по коду.
     *
     * @param string $code Ключ.
     *
     * @return array<string, mixed>|null Строка.
     */
    public function findByCode(string $code): ?array
    {
        return $this->agentRecords->getUnique(ListQuery::fromOptions([
            'filter' => ['code' => $code],
            'limit' => 1,
        ]));
    }

    /**
     * Страница активных агентов.
     *
     * @param int $offset Сдвиг.
     *
     * @return array<int, array<string, mixed>> Строки.
     */
    public function listActive(int $offset): array
    {
        return $this->agentRecords->getList(ListQuery::fromOptions([
            'filter' => ['active' => true],
            'sort' => ['id' => 'asc'],
            'limit' => 500,
            'offset' => $offset,
        ]))->rows();
    }

    /**
     * Пишет новую строку.
     *
     * @param string $code Ключ.
     * @param int $intervalSec Интервал.
     *
     * @return void
     */
    public function add(string $code, int $intervalSec): void
    {
        $this->write(function () use ($code, $intervalSec): mixed {
            $this->agentRecords->add([
                'code' => $code,
                'interval_sec' => $intervalSec,
                'active' => true,
            ]);

            return null;
        });
    }

    /**
     * Пишет момент успешного тика.
     *
     * @param int $agentId Id.
     * @param DateTime $ranAt Момент.
     *
     * @return void
     */
    public function markRan(int $agentId, DateTime $ranAt): void
    {
        $this->write(function () use ($agentId, $ranAt): mixed {
            $this->agentRecords->update($agentId, ['last_run_at' => $ranAt]);

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
     * @throws AgentException Если поля или unique.
     */
    private function write(Closure $work): mixed
    {
        try {
            return $work();
        } catch (UniqueConstraintException $exception) {
            throw new AgentException('AGENT_INVALID', 'Agent code is duplicate', $exception);
        } catch (RowNotFoundException $exception) {
            throw new AgentException('AGENT_INVALID', 'Agent row is missing', $exception);
        } catch (FieldRequiredException $exception) {
            throw new AgentException('AGENT_INVALID', 'Agent field is required', $exception);
        } catch (FieldInvalidException $exception) {
            throw new AgentException('AGENT_INVALID', 'Agent field is invalid', $exception);
        } catch (MapInvalidException $exception) {
            throw new AgentException('AGENT_INVALID', 'Agent map is invalid', $exception);
        }
    }
}
