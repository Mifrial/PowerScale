import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';

/**
 * Источник урона для damage_over_time: значение самого состояния
 * (number/dimensional) или фиксированное число из спеки.
 */
export type StateDamageSource = { kind: 'value' } | { kind: 'fixed'; amount: number };

export type StateEffect =
  | {
      type: 'characteristic_modify';
      /** Целевая характеристика по коду. */
      characteristic_code: string;
      /** Применяется через modify(amount, range): ±3 = ±1 размер. */
      amount: number;
      /** true — умножается на текущее значение состояния (напр. Оглушение). */
      per_unit?: boolean;
    }
  | {
      type: 'damage_over_time';
      /** Откуда берётся урон за тик: значение состояния или фиксированное число. */
      damage: StateDamageSource;
      /** Периодичность: собственный период (значение + шаг). */
      periodicity?: StatePeriodicity;
      /** Затухание: на сколько урон уменьшается каждый тик. */
      decay?: StateDecay;
    }
  | {
      type: 'resource_limit_modify';
      resource_code: string;
      amount: number;
      per_unit?: boolean;
    }
  | {
      type: 'resource_limit_set';
      resource_code: string;
      value: number;
    }
  | {
      type: 'check_advantage';
      /** Отрицательное — помехи. */
      amount: number;
      per_unit?: boolean;
      /** Проверки на попадание. */
      includes_hit?: boolean;
      /**
       * Коды характеристик и их производных (`formula` min/max).
       * Пусто и без `includes_hit` — все проверки.
       */
      characteristic_codes?: string[];
    };
