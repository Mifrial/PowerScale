<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Dto;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;

/**
 * Активная механика: хендлер поставки и payload binding.
 */
final class ResolvedMechanic
{
    /**
     * Собирает разрешённую механику.
     *
     * @param IMechanicHandler $handler Хендлер code@version.
     * @param PurchaseSurchargePayload|null $payload Payload binding или null.
     *
     * @return void
     */
    public function __construct(
        private readonly IMechanicHandler $handler,
        private readonly ?PurchaseSurchargePayload $payload,
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
     * Параметры доплаты из среза правила.
     *
     * @return PurchaseSurchargePayload|null Payload или null.
     */
    public function getPayload(): ?PurchaseSurchargePayload
    {
        return $this->payload;
    }
}
