<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Контекст формулы профиля: базы профиля поверх листа, затем сдвиг карточки.
 */
final class GameStrikeProfileContext
{
    /**
     * Принимает обход формулы.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $formulaEvaluations,
    ) {
    }

    /**
     * Собирает контекст профиля.
     *
     * @param WeaponProfile $profile Профиль.
     * @param FormulaContext $context Базовый контекст листа.
     * @param int $actionCharacteristicModifier Сдвиг карточки в пунктах.
     * @param int $strengthDrop Штраф силы броска в размерах.
     * @param int $gripBonus Бонус удержания к Силе.
     *
     * @return FormulaContext Контекст профиля.
     *
     * @throws RuleInvalidException Если база профиля или сила не найдены.
     */
    public function build(
        WeaponProfile $profile,
        FormulaContext $context,
        int $actionCharacteristicModifier,
        int $strengthDrop,
        int $gripBonus = 0,
    ): FormulaContext {
        $bases = $this->bases($profile, $context);
        $context = $this->withGrip($context, $bases, $gripBonus);
        $bases = $this->withStrengthDrop($profile, $bases, $context, $strengthDrop);
        $context = $this->withBases($profile, $context, $bases);

        return $actionCharacteristicModifier === 0
            ? $context
            : $context->withActionCharacteristicModifier($actionCharacteristicModifier);
    }

    /**
     * Базы характеристик профиля.
     *
     * @param WeaponProfile $profile Профиль.
     * @param FormulaContext $context Контекст листа.
     *
     * @return array<string, CharacteristicNumber> Базы.
     *
     * @throws RuleInvalidException Если формула базы битая.
     */
    private function bases(WeaponProfile $profile, FormulaContext $context): array
    {
        $bases = [];
        foreach ($profile->getActionCharacteristics() as $base) {
            $value = $this->formulaEvaluations->evaluateDimensional($base->getValue(), $context);
            $bases[$base->getCharacteristic()] = new CharacteristicNumber($value->getBase(), $value->getSize());
        }

        return $bases;
    }

    /**
     * Применяет бонус удержания к Силе, если базы Силы нет.
     *
     * @param FormulaContext $context Контекст листа.
     * @param array<string, CharacteristicNumber> $bases Базы профиля.
     * @param int $gripBonus Бонус удержания.
     *
     * @return FormulaContext Контекст.
     */
    private function withGrip(FormulaContext $context, array $bases, int $gripBonus): FormulaContext
    {
        if ($gripBonus === 0 || isset($bases['strength'])) {
            return $context;
        }

        $strength = $context->findCharacteristic('strength');
        if (!$strength instanceof DimensionalNumber) {
            return $context;
        }

        return $context->withCharacteristic(
            'strength',
            (new CharacteristicNumber($strength->getBase(), $strength->getSize()))->modify($gripBonus),
        );
    }

    /**
     * Применяет штраф силы броска к базе Силы.
     *
     * @param WeaponProfile $profile Профиль.
     * @param array<string, CharacteristicNumber> $bases Базы профиля.
     * @param FormulaContext $context Контекст листа.
     * @param int $strengthDrop Штраф в размерах.
     *
     * @return array<string, CharacteristicNumber> Базы.
     *
     * @throws RuleInvalidException Если силы нет.
     */
    private function withStrengthDrop(
        WeaponProfile $profile,
        array $bases,
        FormulaContext $context,
        int $strengthDrop,
    ): array {
        if ($profile->getType() !== 'throw' || $strengthDrop <= 0) {
            return $bases;
        }

        $strength = $bases['strength'] ?? $context->findCharacteristic('strength');
        if (!$strength instanceof DimensionalNumber) {
            throw new RuleInvalidException('Нет силы броска');
        }

        if (!$strength instanceof CharacteristicNumber) {
            $strength = new CharacteristicNumber($strength->getBase(), $strength->getSize());
        }

        $bases['strength'] = $strength->modify(-CharacteristicNumber::BASE_STEP * $strengthDrop);

        return $bases;
    }

    /**
     * Кладёт базы профиля в контекст.
     *
     * @param WeaponProfile $profile Профиль.
     * @param FormulaContext $context Контекст.
     * @param array<string, CharacteristicNumber> $bases Базы.
     *
     * @return FormulaContext Контекст.
     */
    private function withBases(WeaponProfile $profile, FormulaContext $context, array $bases): FormulaContext
    {
        if ($bases === []) {
            return $context;
        }

        return $context->withActionCharacteristics([$profile->getType() => $bases]);
    }
}
