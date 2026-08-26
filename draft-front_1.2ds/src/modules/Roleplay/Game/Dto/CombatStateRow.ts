import type { StateAggregation, StateValueType } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
/** Строка состояния боевой карточки: правило + записи (индексы в списке боя) для правок. */
export interface CombatStateRow {
  ruleId: string;
  code: string;
  name: string;
  iconCode: string | null;
  valueType: StateValueType;
  aggregation: StateAggregation;
  /** Индексы записей правила в списке состояний (для set/remove по индексу). */
  indices: number[];
  /** Есть ли среди записей блок poison (отравление). */
  poison: boolean;
  /** Человекочитаемое значение (flag → null): «3», «4, 1», «3с1». */
  summary: string | null;
}
