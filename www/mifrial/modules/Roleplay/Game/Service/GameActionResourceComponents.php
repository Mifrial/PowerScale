<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithComponents;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ChosenAmount;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\ResourceComponent;

/**
 * Ресурсные компоненты действия и карта выбранных сумм.
 */
final class GameActionResourceComponents
{
    /**
     * Ресурсные компоненты карточки.
     *
     * @param AbilityWithComponents $spec Карточка.
     *
     * @return array<int, ResourceComponent> Компоненты.
     */
    public function of(AbilityWithComponents $spec): array
    {
        $components = [];
        foreach ($spec->getComponents() as $component) {
            if ($component instanceof ResourceComponent) {
                $components[] = $component;
            }
        }

        return $components;
    }

    /**
     * Проверяет карту предложений выбранной суммы.
     *
     * @param array<int|string, mixed> $chosenAmounts Server proposal.
     * @param array<int, ResourceComponent> $components Live components.
     *
     * @return void
     *
     * @throws GameInvalidException If the map is malformed or ambiguous.
     */
    public function assertChosenAmounts(array $chosenAmounts, array $components): void
    {
        if ($chosenAmounts !== [] && array_is_list($chosenAmounts)) {
            throw new GameInvalidException('Game chosen resource amounts are ambiguous');
        }

        $codes = $this->chosenCodes($components);
        foreach ($chosenAmounts as $code => $value) {
            if ($this->invalid($codes, $code, $value)) {
                throw new GameInvalidException('Game chosen resource amounts are invalid');
            }
        }
    }

    /**
     * Коды ресурсов с выбранной суммой.
     *
     * @param array<int, ResourceComponent> $components Компоненты.
     *
     * @return array<string, bool> Коды.
     */
    private function chosenCodes(array $components): array
    {
        $codes = [];
        foreach ($components as $component) {
            if ($component->getAmount() instanceof ChosenAmount) {
                $codes[$component->getResourceCode()] = true;
            }
        }

        return $codes;
    }

    /**
     * Запись карты чужая или битая.
     *
     * @param array<string, bool> $codes Известные коды.
     * @param int|string $code Ключ.
     * @param mixed $value Значение.
     *
     * @return bool Истина, если запись недопустима.
     */
    private function invalid(array $codes, int|string $code, mixed $value): bool
    {
        return $code === ''
            || !isset($codes[$code])
            || (!is_int($value) && !is_array($value));
    }
}
