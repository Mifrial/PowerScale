<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Interface\Value;

/**
 * Контракт локального или агрегированного результата события.
 */
interface IEventResult
{
    /**
     * Добавляет warning без перевода результата в ошибочный статус.
     *
     * @param IEventIssue $issue Структурированная проблема.
     *
     * @return void
     */
    public function addWarning(IEventIssue $issue): void;

    /**
     * Добавляет ошибку и делает результат неуспешным.
     *
     * @param IEventIssue $issue Структурированная проблема.
     *
     * @return void
     */
    public function addError(IEventIssue $issue): void;

    /**
     * Добавляет output payload listener-а.
     *
     * @param IEventPayload $payload Output payload.
     *
     * @return void
     */
    public function addPayload(IEventPayload $payload): void;

    /**
     * Возвращает warnings результата.
     *
     * @return array<int, IEventIssue> Warnings.
     */
    public function getWarnings(): array;

    /**
     * Возвращает ошибки результата.
     *
     * @return array<int, IEventIssue> Ошибки.
     */
    public function getErrors(): array;

    /**
     * Возвращает output payloads результата.
     *
     * @return array<int, IEventPayload> Output payloads.
     */
    public function getPayloads(): array;

    /**
     * Проверяет успешность результата.
     *
     * @return bool true, если ошибок нет.
     */
    public function isSuccessful(): bool;

    /**
     * Проверяет остановку dispatch chain.
     *
     * @return bool true, если цепочка остановлена.
     */
    public function isStopped(): bool;

    /**
     * Возвращает число вызванных listeners.
     *
     * @return int Число вызовов.
     */
    public function getInvokedCount(): int;

    /**
     * Добавляет локальный результат в aggregate.
     *
     * @param IEventResult $result Локальный результат.
     * @param bool $stop Зафиксировать остановку текущего dispatch.
     *
     * @return void
     */
    public function merge(IEventResult $result, bool $stop = false): void;
}
