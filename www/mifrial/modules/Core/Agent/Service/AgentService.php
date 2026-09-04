<?php

declare(strict_types=1);

namespace Mifrial\Core\Agent\Service;

use Mifrial\Core\Agent\Exception\AgentException;
use Mifrial\Core\Agent\Interface\Service\IAgentHandler;
use Mifrial\Core\Agent\Interface\Service\IAgents;
use Mifrial\Core\Agent\Repository\AgentRepository;
use Mifrial\Core\Kernel\Value\DateTime;
use Throwable;

/**
 * Расписание и тик due-агентов.
 */
final class AgentService implements IAgents
{
    /**
     * @var array<string, IAgentHandler>
     */
    private array $handlers = [];

    /**
     * Создаёт сервис.
     *
     * @param AgentRepository $agentRepository Строки.
     *
     * @return void
     */
    public function __construct(
        private readonly AgentRepository $agentRepository,
    ) {
    }

    /**
     * Пишет строку, если кода нет.
     *
     * @param string $code Ключ.
     * @param int $intervalSec Секунды.
     *
     * @return void
     *
     * @throws AgentException Если code/interval недопустимы.
     */
    public function ensureAgent(string $code, int $intervalSec): void
    {
        $normalizedCode = trim($code);
        if ($normalizedCode === '' || $intervalSec < 1) {
            throw new AgentException('AGENT_INVALID', 'Agent code or interval is invalid');
        }

        if ($this->agentRepository->findByCode($normalizedCode) !== null) {
            return;
        }

        try {
            $this->agentRepository->add($normalizedCode, $intervalSec);
        } catch (AgentException $exception) {
            if ($this->agentRepository->findByCode($normalizedCode) === null) {
                throw $exception;
            }
        }
    }

    /**
     * Вешает обработчик процесса.
     *
     * @param string $code Ключ.
     * @param IAgentHandler $handler Обработчик.
     *
     * @return void
     *
     * @throws AgentException Если code пуст.
     */
    public function bindHandler(string $code, IAgentHandler $handler): void
    {
        $normalizedCode = trim($code);
        if ($normalizedCode === '') {
            throw new AgentException('AGENT_INVALID', 'Agent code is invalid');
        }

        $this->handlers[$normalizedCode] = $handler;
    }

    /**
     * Тик активных due-строк.
     *
     * @return void
     */
    public function tick(): void
    {
        $now = DateTime::now();
        $offset = 0;
        do {
            $pageRows = $this->agentRepository->listActive($offset);
            foreach ($pageRows as $agentRow) {
                $this->tickRow($agentRow, $now);
            }

            $offset += 500;
        } while (count($pageRows) === 500);
    }

    /**
     * Один агент страницы.
     *
     * @param array<string, mixed> $agentRow Строка.
     * @param DateTime $now Сейчас.
     *
     * @return void
     */
    private function tickRow(array $agentRow, DateTime $now): void
    {
        $code = (string) $agentRow['code'];
        if (!$this->isDue($agentRow, $now)) {
            return;
        }

        $handler = $this->handlers[$code] ?? null;
        if (!$handler instanceof IAgentHandler) {
            return;
        }

        try {
            $handler->run();
        } catch (Throwable) {
            return;
        }

        $this->agentRepository->markRan((int) $agentRow['id'], DateTime::now());
    }

    /**
     * Пора ли вызывать.
     *
     * @param array<string, mixed> $agentRow Строка.
     * @param DateTime $now Сейчас.
     *
     * @return bool true, если due.
     */
    private function isDue(array $agentRow, DateTime $now): bool
    {
        $lastRunAt = $agentRow['last_run_at'] ?? null;
        if (!$lastRunAt instanceof DateTime) {
            return true;
        }

        $intervalSec = (int) $agentRow['interval_sec'];

        return $lastRunAt->toUnix() + $intervalSec <= $now->toUnix();
    }
}
