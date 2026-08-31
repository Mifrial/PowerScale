import type { CharacterPoisonValue } from '@/modules/Roleplay/Character/Dto/CharacterPoisonValue';

/**
 * Состояние на персонаже — ссылка на правило + значение. Один и тот же stateRuleCode
 * может встречаться несколько раз (повторы); как они объединяются — решает
 * StateSpec.aggregation правила.
 */
export interface CharacterStateValue {
  stateRuleCode: string;
  /** flag: не заполняется; number: целое значение. */
  value?: number;
  /** Только для value_type 'dimensional'. */
  dimensionalValue?: { base: number; size: number };
  /** Только для состояния «Отравление»: применённый яд. */
  poison?: CharacterPoisonValue;
  /** Ходов до следующего тика DOT (`step: turn`). Нет — взять период (period 1 тикает в этот конец хода). */
  dotTurnsLeft?: number;
  /** Только для состояния «Увечье»: срок и флаги этой записи (не суммируется с другими). */
  maim?: {
    permanent: boolean;
    healTotal?: number;
    healUnit?: 'days' | 'decades' | 'months' | 'years';
    lethal?: boolean;
    disfiguring?: boolean;
  };
}
