<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Interface\IDamageAsWounds;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Маркер damage_as_wounds_by_size.
 */
final class DamageAsWoundsHandler implements IMechanicHandler, IDamageAsWounds
{
    /**
     * @return string Код.
     */
    public function getCode(): string
    {
        return 'damage_as_wounds_by_size';
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
