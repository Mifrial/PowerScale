<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Активная механика: хендлер поставки и payload binding.
 */
final class ResolvedMechanic
{
    /**
     * Собирает разрешённую механику.
     *
     * @param IMechanicHandler $handler Хендлер code@version.
     * @param MechanicPayload|null $payload Payload binding или null.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanicHandler $handler,
        private readonly ?MechanicPayload $payload,
    ) {
    }

    /**
     * Хендлер поставки.
     *
     * @return IMechanicHandler Хендлер.
     */
    public function getHandler(): IMechanicHandler
    {
        return $this->handler;
    }

    /**
     * Payload из среза правила.
     *
     * @return MechanicPayload|null Payload или null.
     */
    public function getPayload(): ?MechanicPayload
    {
        return $this->payload;
    }
}
