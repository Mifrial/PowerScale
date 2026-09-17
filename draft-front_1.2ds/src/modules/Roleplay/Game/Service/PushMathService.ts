import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';
import { checkSuccessRatingService } from '@/modules/Roleplay/Rule/init';

/** Расчёт пола, урона и отброса толчка. */
export class PushMathService {
  pushFloor(weight: DimensionalNumberValue): DimensionalNumberValue {
    return new DimensionalNumber(weight).modify(-6, CHARACTERISTIC_BASE_RANGE).value;
  }

  successRating(attackerSuccesses: DimensionalNumberValue, defenderSuccesses: DimensionalNumberValue): number {
    const outcome = checkSuccessRatingService.checkSuccessRating(attackerSuccesses, defenderSuccesses);

    return outcome.passed ? Math.max(0, outcome.rating) : 0;
  }

  raiseDefenderSuccesses(rolled: DimensionalNumberValue, floor: DimensionalNumberValue): DimensionalNumberValue {
    return new DimensionalNumber(rolled).compare(new DimensionalNumber(floor)) < 0 ? floor : rolled;
  }

  crushingDamage(attackerSuccesses: DimensionalNumberValue): DimensionalNumberValue {
    return new DimensionalNumber(attackerSuccesses).modify(-3, CHARACTERISTIC_BASE_RANGE).value;
  }

  knockbackIpari(successRating: number): DimensionalNumberValue {
    return new DimensionalNumber({ base: Math.max(0, successRating), size: 0 }).modify(
      -6,
      CHARACTERISTIC_BASE_RANGE,
    ).value;
  }

  postureRating(successRating: number, divisor = 1): number {
    if (successRating <= 0) return 0;
    if (divisor <= 1) return successRating;

    return Math.floor(successRating / divisor);
  }
}
