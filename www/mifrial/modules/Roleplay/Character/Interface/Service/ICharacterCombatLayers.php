<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Interface\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;

/**
 * Чтение боевых слоёв из документов листа и выборов. Документы не пишет.
 */
interface ICharacterCombatLayers
{
    /**
     * Собирает слои брони, выбранного блока и грантов сопротивления.
     *
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия правил.
     * @param array<string, mixed> $sheet Документ листа.
     * @param array<string, mixed> $choices Документ выборов.
     * @param int|null $blockInventoryId Id надетой строки блока.
     *
     * @return array<int, CharacterCombatLayer> Слои.
     *
     * @throws CharacterInvalidException Если документ или код правила битый.
     * @throws CharacterNotFoundException Если ревизии нет.
     */
    public function project(
        int $spaceId,
        int $rulesRevision,
        array $sheet,
        array $choices,
        ?int $blockInventoryId = null,
    ): array;
}
