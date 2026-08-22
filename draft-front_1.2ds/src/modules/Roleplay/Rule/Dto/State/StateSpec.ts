import type { StateEffect } from '@/modules/Roleplay/Rule/Dto/State/StateEffect';

export type StateValueType = 'flag' | 'number' | 'dimensional';

/** Как объединяются повторы одного правила в списке состояний персонажа. */
export type StateAggregation = 'sum' | 'max' | 'independent';

/**
 * Спека состояния (type='state'): как хранится текущее значение на персонаже
 * (value_type) и какие эффекты состояние оказывает. Источник истины для карточки
 * и для применения эффектов в Обзоре (Phase 2).
 */
export interface StateSpec {
  /** mdi-иконка для ориентации в блоке «Состояния» и списках правил. */
  icon_code?: string | null;
  /**
   * flag        — наличие (есть/нет)
   * number      — целое число
   * dimensional — одно размерное число
   */
  value_type: StateValueType;
  /**
   * Повторы одного правила в списке состояний объединяются по aggregation:
   * sum        — значения суммируются (Горение)
   * max        — берётся наибольшее (Слабость)
   * independent— каждая запись действует отдельно (Раны, каждая со своим значением)
   */
  aggregation: StateAggregation;
  effects?: StateEffect[];
}
