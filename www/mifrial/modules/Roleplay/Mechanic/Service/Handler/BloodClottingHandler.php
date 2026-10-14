<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IBloodClotting;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Маркер blood_clotting. Подписок нет.
 */
final class BloodClottingHandler implements IMechanicHandler, IBloodClotting
{
    /**
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'blood_clotting';
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
