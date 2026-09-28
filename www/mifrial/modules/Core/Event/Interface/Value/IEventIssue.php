<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Interface\Value;

/**
 * Контракт структурированной ошибки или warning события.
 */
interface IEventIssue
{
    /**
     * Возвращает машиночитаемый код проблемы.
     *
     * @return string Код проблемы.
     */
    public function getCode(): string;

    /**
     * Возвращает сообщение проблемы.
     *
     * @return string Сообщение.
     */
    public function getMessage(): string;

    /**
     * Возвращает путь к проблемному полю.
     *
     * @return string|null Путь или null.
     */
    public function getPath(): ?string;
}
