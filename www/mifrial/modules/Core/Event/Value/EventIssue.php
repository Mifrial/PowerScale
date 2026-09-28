<?php

declare(strict_types=1);

namespace Mifrial\Core\Event\Value;

use Mifrial\Core\Event\Exception\EventException;
use Mifrial\Core\Event\Interface\Value\IEventIssue;

/**
 * Стандартная структурированная проблема runtime-события.
 */
final readonly class EventIssue implements IEventIssue
{
    /**
     * Создаёт проблему события.
     *
     * @param string $code Машиночитаемый код.
     * @param string $message Человеческое сообщение.
     * @param string|null $path Путь к проблемному значению.
     *
     * @return void
     *
     * @throws EventException Если код пуст.
     */
    public function __construct(
        private string $code,
        private string $message,
        private ?string $path = null,
    ) {
        if (trim($code) === '') {
            throw new EventException('EVENT_INVALID', 'Event issue code cannot be empty');
        }
    }

    /**
     * Возвращает код проблемы.
     *
     * @return string Код.
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * Возвращает сообщение проблемы.
     *
     * @return string Сообщение.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Возвращает путь к проблемному значению.
     *
     * @return string|null Путь или null.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }
}
