import { SCENE_NAVIGATION } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_NAVIGATION';
import { SCENE_SURFACE } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_SURFACE';

/**
 * Высоты обзора из роста тела.
 *
 * Рост хранится целыми единицами сцены, проценты — целые. Поэтому глаз
 * остаётся целой единицей, а в ипари переводится делением на степень двойки:
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

/** Тело цели целиком, ипари. */
export function sceneBodyIpari(heightUnits: number, resolution: number): number {
  return Math.max(1, heightUnits) / Math.max(1, resolution);
}
