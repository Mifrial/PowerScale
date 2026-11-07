<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Механика-маркер: наследник задаёт код, событий и подписок нет.
 */
abstract class MarkerMechanicHandler implements IMechanicHandler
{
    /**
     * Код семейства задаёт наследник.
     *
     * @return string Код.
     */
    abstract public function getCode(): string;

    /**
     * Поставка контракта маркера.
     *
     * @return string Версия.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Подписок нет.
     *
     * @return array<string, int> Пустая карта.
     */
    public function getSubscriptions(): array
    {
        return [];
    }

    /**
     * Событий не обрабатывает.
     *
     * @param MechanicPayload|null $payload Payload.
     * @param object $context Контекст.
     * @param string $event Событие.
     *
     * @return void
     */
    public function run(?MechanicPayload $payload, object $context, string $event): void
    {
    }
}
