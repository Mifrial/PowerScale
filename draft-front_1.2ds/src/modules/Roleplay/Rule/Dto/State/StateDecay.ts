/**
 * Затухание урона со временем — на сколько урон уменьшается каждый тик.
 * Фиксированное число (простое/размерное), значение характеристики
 * или результат проверки по характеристике.
 */
export type StateDecay =
  | { kind: 'fixed'; value: number }
  | { kind: 'dimensional'; base: number; size: number }
  | { kind: 'characteristic'; characteristic_code: string; modifier?: number }
  | { kind: 'check'; characteristic_code: string };
