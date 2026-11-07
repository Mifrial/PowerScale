<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Interface\IBluntUnconscious;
use Mifrial\Roleplay\Mechanic\Interface\IDamageAsWounds;
use Mifrial\Roleplay\Mechanic\Interface\IInjuryExtraDice;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Чистые расчёты урона, раны и увечья.
 */
final class GameStrikeConsequenceRules
{
    /**
     * Создаёт расчёты последствий.
     *
     * @param ICharacterFormulaContexts $contexts Лист.
     * @param GameStrikeConsequenceStates $states Состояния.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacterFormulaContexts $contexts,
        private readonly GameStrikeConsequenceStates $states,
    ) {
    }

    /**
     * Код раны, если среди хуков есть превращение урона в раны.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     *
     * @return string|null Код раны или null.
     */
    public function cutting(array $hooks): ?string
    {
        foreach ($hooks as $hook) {
            if ($hook['handler'] instanceof IDamageAsWounds) {
                return $this->states->code($hook['payload']);
            }
        }

        return null;
    }

    /**
     * Проверяет нокаут от тупого урона по порогу РУ и стойкости.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $success РУ.
     * @param DimensionalNumber $injury Повреждение.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param DimensionalNumber|null $endurance Стойкость.
     *
     * @return bool Признак нокаута.
     */
    public function blunt(
        array $hooks,
        int $success,
        DimensionalNumber $injury,
        CharacterRuleSlice $slice,
        array $sheet,
        ?DimensionalNumber $endurance = null,
    ): bool {
        foreach ($hooks as $hook) {
            if (!$hook['handler'] instanceof IBluntUnconscious) {
                continue;
            }

            $threshold = $hook['payload']['successAtLeast'] ?? 6;
            if (!is_int($threshold)) {
                $threshold = 6;
            }

            $endurance ??= $this->endurance($slice, $sheet);

            // Канон: нужны оба условия — порог РУ и урон не ниже стойкости.
            return $success >= $threshold && $injury->compare($endurance) >= 0;
        }

        return false;
    }

    /**
     * Сила раны как частное урона и стойкости.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     * @param DimensionalNumber $injury Повреждение.
     *
     * @return int Сила раны.
     *
     * @throws GameInvalidException Если деление размерностей невозможно.
     */
    public function woundStrength(CharacterRuleSlice $slice, array $sheet, DimensionalNumber $injury): int
    {
        $endurance = $this->endurance($slice, $sheet);
        $unit = new DimensionalNumber($endurance->getBase(), $endurance->getSize() - 2);
        try {
            return $injury->divide($unit)->getQuotient();
        } catch (RuleInvalidException $exception) {
            throw new GameInvalidException('Game strike wound is invalid', $exception);
        }
    }

    /**
     * Добавка ⌊РУ / 2⌋, если на типе есть маркер колющего увечья.
     *
     * @param list<array{handler: IMechanicHandler, payload: array<string, mixed>}> $hooks Хуки.
     * @param int $success РУ атаки.
     *
     * @return int Добавка к сложности.
     */
    public function getInjuryExtra(array $hooks, int $success): int
    {
        foreach ($hooks as $hook) {
            if ($hook['handler'] instanceof IInjuryExtraDice) {
                return intdiv(max(0, $success), 2);
            }
        }

        return 0;
    }

    /**
     * Срок увечья по силе. Та же карта, что у проверки увечья на фронте.
     *
     * @param int $strength Сила увечья.
     *
     * @return array{permanent: bool, healTotal: int, healUnit: string}
     */
    public function maimSidecar(int $strength): array
    {
        $spec = match (true) {
            $strength <= 1 => [1, 6, 'days'],
            $strength === 2 => [2, 6, 'days'],
            $strength === 3 => [4, 6, 'days'],
            $strength === 4 => [1, 6, 'decades'],
            $strength === 5 => [1, 5, 'months'],
            default => [1, 6, 'years'],
        };
        $total = 0;
        for ($i = 0; $i < $spec[0]; $i++) {
            $total += random_int(1, $spec[1]);
        }

        return ['permanent' => false, 'healTotal' => $total, 'healUnit' => $spec[2]];
    }

    /**
     * Стойкость персонажа из его листа.
     *
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $sheet Лист.
     *
     * @return DimensionalNumber Стойкость.
     *
     * @throws GameInvalidException Если характеристика стойкости не найдена.
     */
    private function endurance(CharacterRuleSlice $slice, array $sheet): DimensionalNumber
    {
        try {
            $value = $this->contexts->build($sheet)
                ->findCharacteristic(GameStrikeCharacteristicCodes::enduranceCode($slice));
        } catch (CharacterInvalidException $exception) {
            throw new GameInvalidException('Game strike endurance is invalid', $exception);
        }

        if (!$value instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike endurance is invalid');
        }

        return $value;
    }
}
