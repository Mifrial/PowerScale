<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ChosenAmount;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;

/**
 * Текущие значения компонентов «сколько есть» для действия.
 */
final class GameActionChosenAmounts
{
    private readonly CharacterResourceStorage $storage;

    /**
     * Создаёт разбор значений.
     *
     * @return void
     */
    public function __construct()
    {
        $this->storage = new CharacterResourceStorage();
    }

    /**
     * Собирает значения chosen-компонентов.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param AbilityActionSpec $spec Спецификация действия.
     * @param array<string, mixed> $sheet Лист.
     * @param bool $allowZero Разрешить 0.
     *
     * @return array<string, int> Значения по ресурсам.
     *
     * @throws GameInvalidException Если ресурсы битые.
     */
    public function resolve(CharacterRuleSlice $slice, AbilityActionSpec $spec, array $sheet, bool $allowZero): array
    {
        $amounts = [];
        foreach ($spec->getComponents() as $component) {
            if (!$component instanceof ResourceComponent || !$component->getAmount() instanceof ChosenAmount) {
                continue;
            }

            $current = $this->currentScalar($sheet, $component->getResourceCode(), $slice);
            $amount = $current?->getValue() ?? 0;
            if ($amount === 0 && !$allowZero) {
                continue;
            }

            $amounts[$component->getResourceCode()] = $amount;
        }

        return $amounts;
    }

    /**
     * Текущее скалярное значение ресурса.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $resourceCode Ресурс.
     * @param CharacterRuleSlice $slice Срез правил.
     *
     * @return ScalarResourceValue|null Значение или null.
     *
     * @throws GameInvalidException Если ресурсы битые.
     */
    private function currentScalar(
        array $sheet,
        string $resourceCode,
        CharacterRuleSlice $slice,
    ): ?ScalarResourceValue {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game action resources require backfill');
        }

        try {
            foreach ($this->storage->parseRows($rows, $slice) as $row) {
                if ($row->getRuleCode() !== $resourceCode) {
                    continue;
                }

                $current = $row->getCurrent();

                return $current instanceof ScalarResourceValue ? $current : null;
            }
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game action resources are invalid', $exception);
        }

        return null;
    }
}
