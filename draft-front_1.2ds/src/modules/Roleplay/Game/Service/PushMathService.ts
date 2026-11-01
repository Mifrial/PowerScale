import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';
import { checkSuccessRatingService } from '@/modules/Roleplay/Rule/init';

/** Устойчивость, Неустойчивость и отброс толчка. */
export class PushMathService {
  /** Минимальная Устойчивость: вес минус один размер. */
  stabilityFloor(weight: DimensionalNumberValue): DimensionalNumberValue {
    return new DimensionalNumber(weight).modify(-3, CHARACTERISTIC_BASE_RANGE).value;
  }

  successRating(attackerSuccesses: DimensionalNumberValue, defenderSuccesses: DimensionalNumberValue): number {
    const outcome = checkSuccessRatingService.checkSuccessRating(attackerSuccesses, defenderSuccesses);

    return outcome.passed ? Math.max(0, outcome.rating) : 0;
  }

  /** Устойчивость: игнор — минимум, блок — Сила плюс-минус РУ, не ниже минимума. */
  stability(
    reaction: string,
    rating: number,
    defenderStrength: DimensionalNumberValue,
    floor: DimensionalNumberValue,
    cap = 6,
  ): DimensionalNumberValue {
    if (reaction !== 'block') return floor;
    const delta = rating > 0 ? -rating : Math.min(-rating, cap);
    const value = new DimensionalNumber(defenderStrength).modify(delta, CHARACTERISTIC_BASE_RANGE).value;

    return new DimensionalNumber(value).compare(new DimensionalNumber(floor)) < 0 ? floor : value;
  }

  /** Неустойчивость: разница Сил, урезанная делителем типа урона. */
  unstable(attackerStrength: DimensionalNumberValue, stability: DimensionalNumberValue, divisor = 1): number {
    const difference = this.value(attackerStrength) - this.value(stability);
    const unstable = Math.max(0, difference);

    return divisor <= 1 ? unstable : Math.floor(unstable / divisor);
  }

  /** Отброс в ипари: меньше четырёх — нет отброса. */
  knockbackIpari(unstable: number): DimensionalNumberValue | null {
    return unstable >= 4 ? { base: Math.floor(unstable / 4), size: 0 } : null;
  }

  /** Урон профиля со сдвигом толчка. */
  damageModifier(damage: DimensionalNumberValue, modifier: number): DimensionalNumberValue {
    return new DimensionalNumber(damage).modify(modifier, CHARACTERISTIC_BASE_RANGE).value;
  }

  private value(value: DimensionalNumberValue): number {
    const step = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

    return value.base + step * value.size;
  }
}
