import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/init';
import type { RangedHitDifficultyParts } from '@/modules/Roleplay/Character/Dto/RangedHitDifficultyParts';

const SIZE_STEP = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

export class WeaponAttackRangeService {
  readonly defaultFalloff = DEFAULT_FALLOFF;

  /** Полосы дальнобойности: не укладывается в falloff / falloff↑ / falloff↑↑… */
  difficultySizeFromRange(distanceIpari: number, falloff: DimensionalNumberValue): number {
    const base = falloff.base;
    const startSize = falloff.size;
    let extra = 0;
    while (distanceIpari > new DimensionalNumber({ base, size: startSize + extra }).toNumber()) {
      extra += 1;
      if (extra > 24) break;
    }

    return extra;
  }

  /**
   * Сложность попадания ДБ: [max(1, результат проверки) + укрытие]↓ + полосы дальнобойности.
   * Игнор: checkResult = 0. Уклон и блок: checkResult = число успехов своей проверки.
   */
  rangedHitDifficultyParts(
    cover: number,
    checkResult: number,
    distanceIpari: number,
    falloff: DimensionalNumberValue,
  ): RangedHitDifficultyParts {
    const coverSafe = Math.max(0, Math.floor(cover));
    const resultSafe = Math.max(0, Math.floor(checkResult));
    const floorResult = Math.max(1, resultSafe);
    const rangeSize = this.difficultySizeFromRange(distanceIpari, falloff);

    return {
      cover: coverSafe,
      checkResult: resultSafe,
      floorResult,
      rangeSize,
      distanceIpari,
      difficulty: { base: floorResult + coverSafe, size: -1 + rangeSize },
    };
  }

  rangedHitDifficulty(
    cover: number,
    checkResult: number,
    distanceIpari: number,
    falloff: DimensionalNumberValue,
  ): DimensionalNumberValue {
    return this.rangedHitDifficultyParts(cover, checkResult, distanceIpari, falloff).difficulty;
  }

  /** Полные шаги дальнобойности сверх reach: каждый −1 размер силы действия. */
  actionStrengthSizePenalty(distanceIpari: number, reach: number, falloff: DimensionalNumberValue): number {
    const step = Math.max(1, new DimensionalNumber(falloff).toNumber());

    return Math.floor(Math.max(0, distanceIpari - reach) / step);
  }

  profileFormulaContext(
    profile: WeaponProfile,
    context: FormulaContext,
    formula: FormulaEvaluationService,
    strengthSizePenalty = 0,
    actionCharacteristicModifier = 0,
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
        const actionModified = actionCharacteristicModifier
          ? new DimensionalNumber(raw).modify(actionCharacteristicModifier, CHARACTERISTIC_BASE_RANGE).value
          : raw;
        if (!strengthSizePenalty) return actionModified;

        return CharacteristicNumber.from(actionModified).modifyWith(-SIZE_STEP * strengthSizePenalty).value;
      },
    };
  }
}
