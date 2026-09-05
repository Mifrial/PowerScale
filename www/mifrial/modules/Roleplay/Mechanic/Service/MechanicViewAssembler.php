<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service;

use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;

/**
 * Собирает JSON-вид механики из Record.
 */
final class MechanicViewAssembler
{
    /**
     * Собирает JSON-вид механики.
     *
     * @param MechanicRecord $mechanicRecord Поставка.
     *
     * @return array<string, mixed> Mechanic.
     */
    public function assemble(MechanicRecord $mechanicRecord): array
    {
        return [
            'id' => $mechanicRecord->getId(),
            'code' => $mechanicRecord->getCode(),
            'name' => $mechanicRecord->getName(),
            'description' => $mechanicRecord->getDescription(),
            'version' => $mechanicRecord->getHandlerVersion(),
        ];
    }
}
