<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Rule\Spec;

use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicModifier;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\ActionCharacteristicNode;
use Mifrial\Roleplay\Rule\Dto\Spec\Formula\DimensionalFormula;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponDamage;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;

/**
 * Профили удара, броска и выстрела после прибавок урона.
 */
final class ItemModifierItemProfiles
{
    /**
     * Профили атаки. Формула не actionCharacteristic не меняется.
     *
     * @param array<int, WeaponProfile> $profiles Профили.
     * @param array<int, array<string, mixed>> $actions Прибавки.
     * @param string $part Часть блока: weapon или shield.
     *
     * @return array<int, WeaponProfile> Профили.
     */
    public static function profiles(array $profiles, array $actions, string $part): array
    {
        $next = $profiles;
        foreach ($actions as $action) {
            $next = self::applyAction($next, $action, $part);
        }

        return $next;
    }

    /**
     * Одна прибавка ко всем профилям блока.
     *
     * @param array<int, WeaponProfile> $profiles Профили.
     * @param array<string, mixed> $action Прибавка.
     * @param string $part Часть блока: weapon или shield.
     *
     * @return array<int, WeaponProfile> Профили.
     */
    private static function applyAction(array $profiles, array $action, string $part): array
    {
        if ($action['part'] !== $part || $action['delta'] === 0) {
            return $profiles;
        }

        $rewritten = [];
        foreach ($profiles as $profile) {
            $rewritten[] = self::profile($profile, $action);
        }

        return $rewritten;
    }

    /**
     * Один профиль. Чужой вид и чужой тип урона не меняются.
     *
     * @param WeaponProfile $profile Профиль.
     * @param array<string, mixed> $action Прибавка.
     *
     * @return WeaponProfile Профиль.
     */
    private static function profile(WeaponProfile $profile, array $action): WeaponProfile
    {
        if (!self::profileMatches($profile, $action)) {
            return $profile;
        }

        $damage = $profile->getDamage();
        $penetration = $profile->getPenetration();
        if ($action['field'] === 'damage') {
            $damage = self::damage($damage, $action['delta'], $action['source']);
        }

        if ($action['field'] === 'penetration') {
            $penetration = self::formula($penetration, $action['delta'], $action['source']);
        }

        return new WeaponProfile(
            $profile->getType(),
            $profile->getDistance(),
            $damage,
            $penetration,
            $profile->getAccuracy(),
            $profile->getActionCharacteristics(),
            $profile->getFalloff(),
            $profile->getDodgeBenefit(),
            $profile->getAmmunition(),
        );
    }

    /**
     * Профиль входит в списки операции. Пустой список — все.
     *
     * @param WeaponProfile $profile Профиль.
     * @param array<string, mixed> $action Прибавка.
     *
     * @return bool true, если профиль меняется.
     */
    private static function profileMatches(WeaponProfile $profile, array $action): bool
    {
        $profiles = $action['profiles'];
        if ($profiles !== [] && !in_array($profile->getType(), $profiles, true)) {
            return false;
        }

        $damageTypes = $action['damageTypes'];
        $damageType = $profile->getDamage()->getDamageTypeCode();

        return $damageTypes === [] || ($damageType !== null && in_array($damageType, $damageTypes, true));
    }

    /**
     * Урон с модификатором формулы.
     *
     * @param WeaponDamage $damage Урон.
     * @param int $delta Слагаемое.
     * @param string|null $source Код source операции.
     *
     * @return WeaponDamage Урон.
     */
    private static function damage(WeaponDamage $damage, int $delta, ?string $source): WeaponDamage
    {
        return new WeaponDamage(self::formula($damage->getFormula(), $delta, $source), $damage->getDamageTypeCode());
    }

    /**
     * Дописывает источник операции, не код модификатора предмета.
     *
     * @param DimensionalFormula $formula Формула.
     * @param int $delta Слагаемое.
     * @param string|null $source Код source операции.
     *
     * @return DimensionalFormula Формула.
     */
    private static function formula(DimensionalFormula $formula, int $delta, ?string $source): DimensionalFormula
    {
        if (!$formula instanceof ActionCharacteristicNode) {
            return $formula;
        }

        $modifiers = $formula->getModifiers();
        $modifiers[] = new ActionCharacteristicModifier($delta, $source, null);

        return new ActionCharacteristicNode(
            $formula->getAction(),
            $formula->getCharacteristic(),
            $formula->getMultiplier(),
            $modifiers,
        );
    }
}
