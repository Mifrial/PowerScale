<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Лимит ресурса листа: база строки плюс вклады.
 *
 * Отличается от текущего значения: лимит не зависит от уже потраченного.
 */
final class GameResourceLimit
{
    private readonly CharacterResourceStorage $resources;

    /**
     * Создаёт расчёт.
     *
     * @return void
     */
    public function __construct()
    {
        $this->resources = new CharacterResourceStorage();
    }

    /**
     * Лимит ресурса из листа.
     *
     * @param CharacterRuleSlice $slice Срез ревизии.
     * @param array<string, mixed> $sheet Лист.
     * @param string $resourceCode Код ресурса.
     *
     * @return int Лимит.
     *
     * @throws GameInvalidException Если строки ресурсов битые.
     */
    public function of(CharacterRuleSlice $slice, array $sheet, string $resourceCode): int
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game resource limit is invalid');
        }

        try {
            foreach ($this->resources->parseRows($rows, $slice) as $row) {
                if ($row->getRuleCode() !== $resourceCode) {
                    continue;
                }

                $base = $row->getBase();
                $limit = $base instanceof DimensionalNumber ? $base->toInteger() : (is_int($base) ? $base : 0);
                foreach ($row->getBonuses() as $bonus) {
                    $limit += $bonus->getDelta();
                }

                return max(0, $limit);
            }
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game resource limit is invalid', $exception);
        }

        return 0;
    }
}
