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
    };
