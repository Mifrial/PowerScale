<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Value;

use Mifrial\Core\Event\Interface\Value\IEventIssue;
use Mifrial\Core\Event\Interface\Value\IEventPayload;
use Mifrial\Core\Event\Interface\Value\IEventResult;

/**
 * Стандартный локальный или агрегированный результат события.
 */
final class EventResult implements IEventResult
{
    /**
     * @var array<int, IEventIssue>
     */
    private array $warnings = [];

    /**
     * @var array<int, IEventIssue>
     */
    private array $errors = [];

    /**
     * @var array<int, IEventPayload>
     */
    private array $payloads = [];

    private int $invokedCount = 0;

    private bool $stopped = false;

    private bool $failed = false;

    /**
     * Добавляет warning.
     *
     * @param IEventIssue $issue Структурированная проблема.
     *
     * @return void
     */
    public function addWarning(IEventIssue $issue): void
    {
        $this->warnings[] = $issue;
    }

    /**
     * Добавляет ошибку.
     *
     * @param IEventIssue $issue Структурированная проблема.
     *
     * @return void
     */
    public function addError(IEventIssue $issue): void
    {
        $this->errors[] = $issue;
    }

    /**
     * Добавляет output payload.
     *
     * @param IEventPayload $payload Output payload.
     *
     * @return void
     */
    public function addPayload(IEventPayload $payload): void
    {
        $this->payloads[] = $payload;
    }

    /**
     * Возвращает warnings.
     *
     * @return array<int, IEventIssue> Warnings.
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Возвращает ошибки.
     *
     * @return array<int, IEventIssue> Ошибки.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Возвращает output payloads.
     *
     * @return array<int, IEventPayload> Output payloads.
     */
    public function getPayloads(): array
    {
        return $this->payloads;
    }

    /**
     * Проверяет успешность результата.
     *
     * @return bool true, если ошибок нет.
     */
    public function isSuccessful(): bool
    {
        return !$this->failed && $this->errors === [];
    }

    /**
     * Проверяет остановку dispatch chain.
     *
     * @return bool true, если цепочка остановлена.
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Возвращает число вызванных listeners.
     *
     * @return int Число вызовов.
     */
    public function getInvokedCount(): int
    {
        return $this->invokedCount;
    }

    /**
     * Объединяет локальный результат с aggregate.
     *
     * @param IEventResult $result Локальный результат.
     * @param bool $stop Зафиксировать остановку текущего dispatch.
     *
     * @return void
     */
    public function merge(IEventResult $result, bool $stop = false): void
    {
        ++$this->invokedCount;
        $this->stopped = $this->stopped || $stop;
        $this->failed = $this->failed || $stop || !$result->isSuccessful();

        foreach ($result->getWarnings() as $issue) {
            $this->addWarning($issue);
        }

        foreach ($result->getErrors() as $issue) {
            $this->addError($issue);
        }

        foreach ($result->getPayloads() as $payload) {
            $this->addPayload($payload);
        }
    }
}
