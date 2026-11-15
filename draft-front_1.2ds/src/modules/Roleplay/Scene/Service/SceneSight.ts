import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';

/**
 * Высоты обзора из роста тела.
 *
 * Рост хранится целыми единицами сцены, проценты — целые. Поэтому глаз и цель
 * остаются целыми единицами, а в ипари переводятся делением на степень двойки:
 * дробных чисел в данных нет, результат — размерное число `{base|size}`.
 */

/** Рост тела по умолчанию, единицы сцены. */
export function sceneBodyHeight(resolution: number): number {
  const base = Math.max(1, resolution);

  return Math.max(1, Math.round(base * SCENE_NAVIGATION.bodyHeightIpari));
}

/** Глаза наблюдателя, ипари: 90% роста. */
export function sceneSightEyeIpari(heightUnits: number, resolution: number): number {
  const base = Math.max(1, resolution);
  const height = Math.max(1, heightUnits);

  return Math.round((height * SCENE_SURFACE.eyePercent) / 100) / base;
}

/** Точка тела цели, ипари: 75% роста. */
export function sceneSightTargetIpari(heightUnits: number, resolution: number): number {
  const base = Math.max(1, resolution);
  const height = Math.max(1, heightUnits);

  return Math.round((height * SCENE_SURFACE.targetPercent) / 100) / base;
}
