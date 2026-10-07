<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Dto\CharacterCombatLayer;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterCombatLayers;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;

/**
 * Проекция боевых слоёв. Строку персонажа не пишет.
 */
final class CharacterCombatLayers implements ICharacterCombatLayers
{
    private readonly CharacterCombatItemLayers $items;

    private readonly CharacterCombatGrantLayers $grants;

    /**
     * Создаёт проекцию.
     *
     * @param ICharacterRuleSlices $slices Срез ревизии.
     * @param ICharacterFormulaContexts $contexts Контекст листа.
     * @param IFormulaEvaluations $evaluations Расчёт формул.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterRuleSlices $slices,
        ICharacterFormulaContexts $contexts,
        IFormulaEvaluations $evaluations,
    ) {
        $this->items = new CharacterCombatItemLayers();
        $this->grants = new CharacterCombatGrantLayers($contexts, $evaluations);
    }

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
    ): array {
        $slice = $this->slices->get($spaceId, $rulesRevision);
        $inventory = $this->inventory($choices);
        $this->assertIds($inventory);
        $layers = $this->items->armor($slice, $inventory);
        if ($blockInventoryId !== null) {
            array_push($layers, ...$this->items->block($slice, $inventory, $blockInventoryId));
        }

        array_push($layers, ...$this->grants->collect($slice, $sheet, $choices));

        return $layers;
    }

    /**
     * Список инвентаря.
     *
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<int, mixed> Строки.
     *
     * @throws CharacterInvalidException Если список битый.
     */
    private function inventory(array $choices): array
    {
        $inventory = $choices['inventory'] ?? [];
        if (!is_array($inventory)) {
            throw new CharacterInvalidException('Character combat layers are invalid');
        }

        return $inventory;
    }

    /**
     * Повтор id отклоняется.
     *
     * @param array<int, mixed> $inventory Строки.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если id повторяется или битый.
     */
    private function assertIds(array $inventory): void
    {
        $seen = [];
        foreach ($inventory as $row) {
            if (!is_array($row)) {
                throw new CharacterInvalidException('Character combat layers are invalid');
            }

            if (!array_key_exists('id', $row)) {
                continue;
            }

            $id = $row['id'];
            if (!is_int($id) || isset($seen[$id])) {
                throw new CharacterInvalidException('Character combat layers are invalid');
            }

            $seen[$id] = true;
        }
    }
}
