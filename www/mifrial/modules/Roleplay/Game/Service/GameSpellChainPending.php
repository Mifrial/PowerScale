<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellChain;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\SpellDamage;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Отложенное продолжение цепи: остаток урона, цель и форма прыжка.
 *
 * Запись живёт в pending_spell_cast боя между прыжками. Всё, что нужно
 * прыжку, записано здесь: карточка каста могла измениться, а цепь уже
 * оплачена ОД и проверкой.
 */
final class GameSpellChainPending
{
    private const INVALID = 'Game spell chain is invalid';

    /**
     * Собирает продолжение цепи после первого попадания.
     *
     * @param string $castId Ключ каста.
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param array<string, mixed> $damage Итог урона первого попадания.
     * @param string $ruleCode Код улучшения цепи.
     * @param array{id: string, parentId: string, kind: string}|null $thread Нить чата.
     * @param int $rating Остаток успеха каста после насыщения.
     *
     * @return array<string, mixed>|null Продолжение или null, если цепь не открывается.
     */
    public function build(
        string $castId,
        GameSpellCastPrepared $prepared,
        array $damage,
        string $ruleCode,
        ?array $thread,
        int $rating,
    ): ?array {
        $chain = $prepared->upgrades->getChain();
        $amount = $damage['spellDamage'] ?? null;
        $spellSr = $damage['spellSr'] ?? null;
        $target = $prepared->damageTarget;
        $spec = $prepared->spell->getSpell()->getDamage();
        if (
            !$chain instanceof SpellChain
            || !$amount instanceof DimensionalNumber
            || !is_int($spellSr)
            || $spellSr <= 0
            || $target === null
            || $spec === null
        ) {
            return null;
        }

        return [
            'phase' => 'chain',
            'castId' => $castId,
            'actor' => ['type' => $prepared->casterKind, 'id' => $prepared->casterId],
            'spellCode' => $prepared->spellCode,
            'pathCode' => $prepared->pathCode,
            'ruleCode' => $ruleCode,
            'damageTypeCode' => $spec->getDamageTypeCode(),
            'freeIpari' => $spec->getFreeIpari(),
            'sizePerExtraIpari' => $spec->getSizePerExtraIpari(),
            'damageMin' => $this->pair($spec->getMin()),
            'amount' => ['base' => $amount->getBase(), 'size' => $amount->getSize()],
            'min' => $this->pair($chain->getMin()),
            'damageSizePerHop' => $chain->getDamageSizePerHop(),
            'retarget' => $chain->getRetarget(),
            'sameTarget' => $chain->getSameTarget(),
            'spellSr' => $spellSr,
            'rating' => $rating,
            'penetration' => $prepared->upgrades->getResistancePenetration(),
            'lastKey' => $target['type'] . ':' . $target['id'],
            'hit' => [$target['type'] . ':' . $target['id']],
            'thread' => $thread,
            'createdAt' => time(),
        ];
    }

    /**
     * Проверяет и возвращает продолжение цепи из строки боя.
     *
     * @param mixed $value Значение колонки.
     *
     * @return array<string, mixed> Продолжение.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    public function from(mixed $value): array
    {
        if (
            !is_array($value)
            || array_is_list($value)
            || ($value['phase'] ?? null) !== 'chain'
        ) {
            throw new GameInvalidException(self::INVALID);
        }

        foreach (['castId', 'spellCode', 'pathCode', 'ruleCode', 'damageTypeCode', 'lastKey'] as $key) {
            if (!is_string($value[$key] ?? null) || $value[$key] === '') {
                throw new GameInvalidException(self::INVALID);
            }
        }

        foreach (['damageSizePerHop', 'spellSr', 'penetration', 'createdAt', 'rating'] as $key) {
            if (!is_int($value[$key] ?? null)) {
                throw new GameInvalidException(self::INVALID);
            }
        }

        foreach (['freeIpari', 'sizePerExtraIpari'] as $key) {
            if (($value[$key] ?? null) !== null && !is_int($value[$key])) {
                throw new GameInvalidException(self::INVALID);
            }
        }

        if (!is_array($value['actor'] ?? null) || !$this->isPair($value['amount'] ?? null)) {
            throw new GameInvalidException(self::INVALID);
        }

        foreach (['damageMin', 'min'] as $key) {
            if (!array_key_exists($key, $value) || (($value[$key] !== null) && !$this->isPair($value[$key]))) {
                throw new GameInvalidException(self::INVALID);
            }
        }

        $hit = $value['hit'] ?? null;
        if (!is_array($hit) || !array_is_list($hit) || $hit === []) {
            throw new GameInvalidException(self::INVALID);
        }

        foreach ($hit as $key) {
            if (!is_string($key) || $key === '') {
                throw new GameInvalidException(self::INVALID);
            }
        }

        return $value;
    }

    /**
     * Форма цепи из записи.
     *
     * @param array<string, mixed> $chain Продолжение.
     *
     * @return SpellChain Цепь.
     *
     * @throws GameInvalidException Если минимум битый.
     */
    public function chainSpec(array $chain): SpellChain
    {
        return new SpellChain(
            (int) $chain['damageSizePerHop'],
            $this->number($chain['min']),
            (string) $chain['retarget'],
            (string) $chain['sameTarget'],
        );
    }

    /**
     * Карточка урона из записи: нужны только снижение и минимум.
     *
     * @param array<string, mixed> $chain Продолжение.
     *
     * @return SpellDamage Урон.
     */
    public function damageSpec(array $chain): SpellDamage
    {
        return new SpellDamage(
            (string) $chain['damageTypeCode'],
            '',
            [],
            is_int($chain['freeIpari'] ?? null) ? $chain['freeIpari'] : null,
            is_int($chain['sizePerExtraIpari'] ?? null) ? $chain['sizePerExtraIpari'] : null,
            is_array($chain['damageMin'] ?? null) ? $this->number($chain['damageMin']) : null,
        );
    }

    /**
     * Остаток урона из записи.
     *
     * @param array<string, mixed> $chain Продолжение.
     *
     * @return CharacteristicNumber Урон.
     */
    public function amount(array $chain): CharacteristicNumber
    {
        $value = $this->number($chain['amount']);

        return new CharacteristicNumber($value->getBase(), $value->getSize());
    }

    /**
     * Продолжение после попадания прыжка.
     *
     * @param array<string, mixed> $chain Продолжение.
     * @param DimensionalNumber $amount Урон прыжка.
     * @param string $key Поражённая цель.
     * @param int $rating Остаток успеха после траты.
     *
     * @return array<string, mixed> Новое продолжение.
     */
    public function advance(array $chain, DimensionalNumber $amount, string $key, int $rating): array
    {
        $hit = is_array($chain['hit'] ?? null) ? $chain['hit'] : [];
        $hit[] = $key;
        $chain['amount'] = ['base' => $amount->getBase(), 'size' => $amount->getSize()];
        $chain['lastKey'] = $key;
        $chain['hit'] = array_values($hit);
        $chain['rating'] = $rating;

        return $chain;
    }

    /**
     * Блок цепи для ответа.
     *
     * @param array<string, mixed> $chain Продолжение.
     *
     * @return array<string, mixed> Блок.
     */
    public function view(array $chain): array
    {
        return [
            'castId' => $chain['castId'],
            'ruleCode' => $chain['ruleCode'],
            'spellCode' => $chain['spellCode'],
            'damageTypeCode' => $chain['damageTypeCode'],
            'amount' => $chain['amount'],
            'min' => $chain['min'],
            'damageSizePerHop' => $chain['damageSizePerHop'],
            'sameTarget' => $chain['sameTarget'],
            'retarget' => $chain['retarget'],
            'spellSr' => $chain['spellSr'],
            'rating' => $chain['rating'],
            'freeIpari' => $chain['freeIpari'],
            'sizePerExtraIpari' => $chain['sizePerExtraIpari'],
            'damageMin' => $chain['damageMin'],
            'lastKey' => $chain['lastKey'],
            'hit' => $chain['hit'],
        ];
    }

    /**
     * Пара размерного числа для записи.
     *
     * @param DimensionalNumber|null $value Число.
     *
     * @return array{base: int, size: int}|null Пара или null.
     */
    private function pair(?DimensionalNumber $value): ?array
    {
        return $value === null ? null : ['base' => $value->getBase(), 'size' => $value->getSize()];
    }

    /**
     * Число из записи.
     *
     * @param mixed $value Пара.
     *
     * @return DimensionalNumber Число.
     *
     * @throws GameInvalidException Если пара битая.
     */
    private function number(mixed $value): DimensionalNumber
    {
        if (!$this->isPair($value)) {
            throw new GameInvalidException(self::INVALID);
        }

        return new DimensionalNumber($value['base'], $value['size']);
    }

    /**
     * Пара размерного числа.
     *
     * @param mixed $value Значение.
     *
     * @return bool true, если это пара.
     */
    private function isPair(mixed $value): bool
    {
        return is_array($value)
            && is_int($value['base'] ?? null)
            && is_int($value['size'] ?? null);
    }
}
