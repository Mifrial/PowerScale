import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberBaseRange } from '@/modules/Core/Engine/Dto/DimensionalNumberBaseRange';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Диапазон баз характеристик (3–5). Шаг размера = (max − min + 1) = 3 пункта:
 * модификатор −3 у Силы 5 средних даёт 5↓ (маленькие), а не 2.
 */
export const CHARACTERISTIC_BASE_RANGE: DimensionalNumberBaseRange = { min: 3, max: 5 };

/**
 * Размерное число шкалы характеристик (база 3–5). modifyWith ходит по шкале
 * (модификатор +1 на {5|0} даёт {3|+1}), modifyDiffTo переводит разницу в пункты.
 */
export class CharacteristicNumber extends DimensionalNumber {
  static from(value: DimensionalNumberValue): CharacteristicNumber {
    return new CharacteristicNumber(value);
  }

  static fromBase(base: number): CharacteristicNumber {
    return new CharacteristicNumber({ base, size: 0 });
  }

  modifyWith(delta: number): CharacteristicNumber {
    return new CharacteristicNumber(this.modify(delta, CHARACTERISTIC_BASE_RANGE).value);
  }

  modifyDiffTo(other: DimensionalNumber): number {
    const step = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

    return this.value.base + step * this.value.size - (other.value.base + step * other.value.size);
  }
}
