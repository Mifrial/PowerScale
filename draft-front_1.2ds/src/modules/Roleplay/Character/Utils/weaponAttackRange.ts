import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/** Дальнобойность по умолчанию, если в профиле не указана. */
export const DEFAULT_FALLOFF: DimensionalNumberValue = { base: 5, size: 0 };

const SIZE_STEP = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

/** Полосы дальнобойности: не укладывается в falloff / falloff↑ / falloff↑↑… */
export function difficultySizeFromRange(distanceIpari: number, falloff: DimensionalNumberValue): number {
  const base = falloff.base;
  const startSize = falloff.size;
  let extra = 0;
  while (distanceIpari > new DimensionalNumber({ base, size: startSize + extra }).toNumber()) {
    extra += 1;
    if (extra > 24) break;
  }

  return extra;
}

/** Полные шаги дальнобойности сверх reach: каждый −1 размер силы действия. */
export function actionStrengthSizePenalty(
  distanceIpari: number,
  reach: number,
  falloff: DimensionalNumberValue,
): number {
  const step = Math.max(1, new DimensionalNumber(falloff).toNumber());

  return Math.floor(Math.max(0, distanceIpari - reach) / step);
}

export function profileFormulaContext(
  profile: WeaponProfile,
  context: FormulaContext,
  formula: FormulaEvaluationService,
  strengthSizePenalty = 0,
): FormulaContext {
  return {
    ...context,
    actionCharacteristicValue: (action, characteristic) => {
      const entry = profile.action_characteristics?.find((item) => item.characteristic === characteristic);
      const raw =
        entry !== undefined
          ? formula.evaluateDimensional(entry.value, context)
          : (context.actionCharacteristicValue?.(action, characteristic) ??
            context.characteristicValues.get(characteristic));
      if (!raw) return undefined;
      if (!strengthSizePenalty) return raw;

      return CharacteristicNumber.from(raw).modifyWith(-SIZE_STEP * strengthSizePenalty).value;
    },
  };
}
