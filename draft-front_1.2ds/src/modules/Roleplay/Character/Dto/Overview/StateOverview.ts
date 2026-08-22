import type { StateAggregation } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';

/**
 * Состояние в обзоре — одна запись после объединения повторов правила
 * (sum/max) или одна запись на каждый повтор (independent).
 */
export interface StateEntryOverview {
  id: string;
  ruleId: string;
  name: string;
  iconCode: string | null;
  /** Итоговое значение (flag → null): «3», «4», «2с1». */
  valueLabel: string | null;
  /** Сколько записей правила объединено в эту строку. */
  count: number;
  aggregation: StateAggregation;
  /** Профиль урона со временем одной строкой или null. */
  dotLabel: string | null;
  href: string;
  isResolved: boolean;
}
