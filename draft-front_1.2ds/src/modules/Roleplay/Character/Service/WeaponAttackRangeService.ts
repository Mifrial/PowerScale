import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import type { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/Constant/CHARACTERISTIC_BASE_RANGE';
import { DEFAULT_FALLOFF } from '@/modules/Roleplay/Character/Constant/Weapon/DEFAULT_FALLOFF';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import type { RangedHitDifficultyParts } from '@/modules/Roleplay/Character/Dto/RangedHitDifficultyParts';
import type { DistanceValue } from '@/modules/Roleplay/Rule/Dto/Item/DistanceValue';

const SIZE_STEP = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

export class WeaponAttackRangeService {
  readonly defaultFalloff = DEFAULT_FALLOFF;

  /** Полоса дальнобойности: 1 на пороге, 2 на falloff↑, 3 на falloff↑↑… */
  difficultySizeFromRange(distanceIpari: number, falloff: DimensionalNumberValue): number {
    const distanceValue =
      DimensionalNumber.fromDecimal(distanceIpari) ?? new DimensionalNumber({ base: distanceIpari, size: 0 });
    const base = falloff.base;
    const startSize = falloff.size;
    let extra = 0;
    while (distanceValue.compare(new DimensionalNumber({ base, size: startSize + extra })) > 0) {
      extra += 1;
      if (extra > 24) break;
    }

    return extra + 1;
  }

  /** Переводит DistanceValue в ипари: step умножается на шаг расы. */
  resolveDistanceValue(
    value: DistanceValue,
    context: Parameters<FormulaEvaluationService['evaluateDimensional']>[1],
    formula: FormulaEvaluationService,
    movementStep: DimensionalNumberValue = { base: 1, size: 0 },
  ): DimensionalNumberValue {
    const evaluated = formula.evaluateDimensional(value.value, context);
    if (value.type === 'ipari') return evaluated;

    return new DimensionalNumber(evaluated).multiplyDimensional(new DimensionalNumber(movementStep)).value;
  }

  /**
   * Сложность попадания ДБ: (успехи + ½ + укрытие), сдвинутые на полосы дальнобойности.
   * Игнор: checkResult = 0. Уклон и блок: checkResult = число успехов своей проверки.
   */
  rangedHitDifficultyParts(
    cover: number | DimensionalNumberValue,
    checkResult: number | DimensionalNumberValue,
    distanceIpari: number,
    falloff: DimensionalNumberValue,
  ): RangedHitDifficultyParts {
    const coverValue = typeof cover === 'number' ? { base: Math.max(0, cover), size: 0 } : cover;
    const resultValue = typeof checkResult === 'number' ? { base: Math.max(0, checkResult), size: 0 } : checkResult;
    const rangeSize = this.difficultySizeFromRange(distanceIpari, falloff);
    const base = new DimensionalNumber(resultValue)
      .add(new DimensionalNumber({ base: 1, size: -1 }))
      .add(new DimensionalNumber(coverValue));

    return {
      cover: new DimensionalNumber(coverValue).toNumber(),
      checkResult: new DimensionalNumber(resultValue).toNumber(),
      floorResult: new DimensionalNumber(resultValue).toNumber(),
      rangeSize,
      distanceIpari,
      difficulty: base.withSize(base.value.size + rangeSize - 1).value,
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
  actionStrengthSizePenalty(
    distance: number | DimensionalNumberValue,
    reach: number | DimensionalNumberValue,
    falloff: DimensionalNumberValue,
  ): number {
    const distanceValue =
      typeof distance === 'number'
        ? (DimensionalNumber.fromDecimal(distance) ?? new DimensionalNumber({ base: distance, size: 0 }))
        : new DimensionalNumber(distance);
    const reachValue =
      typeof reach === 'number'
        ? (DimensionalNumber.fromDecimal(reach) ?? new DimensionalNumber({ base: reach, size: 0 }))
        : new DimensionalNumber(reach);
    const step = new DimensionalNumber(falloff);
    if (step.compare(new DimensionalNumber({ base: 0, size: 0 })) <= 0) return 0;

    let penalty = 0;
    let threshold = reachValue.add(step);
    while (distanceValue.compare(threshold) >= 0 && penalty < 100) {
      penalty += 1;
      threshold = threshold.add(step);
    }

    return penalty;
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
