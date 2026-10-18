<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\IReliabilityCut;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Набранные РУ атаки срезают слои с надёжностью не выше этого рейтинга.
 */
final class PaySrVsReliabilityHandler implements IMechanicHandler, IReliabilityCut
{
    /**
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'pay_sr_vs_reliability';
    }

    /**
     * @return string Версия.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * @return array<string, int> Пустая карта.
     */
    public function getSubscriptions(): array
    {
        return [];
    }

    /**
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
