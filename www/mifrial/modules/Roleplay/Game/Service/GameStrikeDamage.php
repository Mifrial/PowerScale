<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Interface\Service\IFormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон, пробитие и сила подготовленного удара.
 *
 * Калькулятор читает только снимок атаки: формулу профиля и уже собранный
 * контекст. В порты Character/Game он не ходит.
 */
final class GameStrikeDamage
{
    /**
     * Принимает обход формулы.
     *
     * @param IFormulaEvaluations $formulaEvaluations Обход формулы.
     * @param GameStrikeAttackFactory|null $attacks Сборка снимка атаки.
     *
     * @return void
     */
    public function __construct(
        private readonly IFormulaEvaluations $formulaEvaluations,
        private readonly ?GameStrikeAttackFactory $attacks = null,
    ) {
    }

    /**
     * Снимок удара по запросу.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return GameStrikeAttack Снимок.
     *
     * @throws GameInvalidException Если фабрика не подключена или атака битая.
     */
    public function attack(GameStrikeTarget $target): GameStrikeAttack
    {
        if ($this->attacks === null) {
            throw new GameInvalidException('Game strike attack factory is missing');
        }

        return $this->attacks->build($target);
    }

    /**
     * Урон запроса удара.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return DimensionalNumber Урон.
     *
     * @throws GameInvalidException Если атака не собирается.
     * @throws RuleInvalidException Если формула урона битая.
     */
    public function evaluate(GameStrikeTarget $target): DimensionalNumber
    {
        return $this->amount($this->attack($target));
    }

    /**
     * Пробитие запроса удара.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return DimensionalNumber Пробитие.
     *
     * @throws GameInvalidException Если атака не собирается.
     * @throws RuleInvalidException Если формула пробития битая.
     */
    public function evaluatePenetration(GameStrikeTarget $target): DimensionalNumber
    {
        return $this->penetration($this->attack($target));
    }

    /**
     * Сила запроса удара.
     *
     * @param GameStrikeTarget $target Запрос удара.
     *
     * @return CharacteristicNumber Сила.
     *
     * @throws GameInvalidException Если атака не собирается или силы нет.
     */
    public function strikeStrength(GameStrikeTarget $target): CharacteristicNumber
    {
        return $this->strength($this->attack($target));
    }

    /**
     * Урон профиля по его формуле.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     *
     * @return DimensionalNumber Урон.
     *
     * @throws RuleInvalidException Если формула урона битая.
     */
    public function amount(GameStrikeAttack $attack): DimensionalNumber
    {
        return $this->formulaEvaluations->evaluateDimensional(
            $attack->profile->getDamage()->getFormula(),
            $attack->context,
        );
    }

    /**
     * Пробитие профиля по его формуле.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     *
     * @return DimensionalNumber Пробитие.
     *
     * @throws RuleInvalidException Если формула пробития битая.
     */
    public function penetration(GameStrikeAttack $attack): DimensionalNumber
    {
        return $this->formulaEvaluations->evaluateDimensional(
            $attack->profile->getPenetration(),
            $attack->context,
        );
    }

    /**
     * Сила удара: база профиля либо характеристика, затем сдвиг карточки.
     *
     * @param GameStrikeAttack $attack Снимок атаки.
     *
     * @return CharacteristicNumber Сила удара.
     *
     * @throws GameInvalidException Если силы нет.
     */
    public function strength(GameStrikeAttack $attack): CharacteristicNumber
    {
        $strength = $attack->context->findActionCharacteristic($attack->profile->getType(), 'strength')
            ?? $attack->context->findCharacteristic('strength');
        if (!$strength instanceof DimensionalNumber) {
            throw new GameInvalidException('Game strike strength is invalid');
        }

        $number = $strength instanceof CharacteristicNumber
            ? $strength
            : new CharacteristicNumber($strength->getBase(), $strength->getSize());
        $delta = $attack->context->getActionCharacteristicModifier();

        return $delta === 0 ? $number : $number->modify($delta);
    }
}
