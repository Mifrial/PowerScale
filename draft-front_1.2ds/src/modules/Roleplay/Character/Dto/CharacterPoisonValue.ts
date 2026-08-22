import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';

/**
 * Применённое отравление на персонаже (для состояния «Отравление»).
 * Правило-яд (poisonRuleId) — шаблон с «по умолчанию»; фактические параметры
 * могут быть заданы здесь (предмет/способность) или придуманы мастером.
 */
export interface CharacterPoisonValue {
  /** Правило-яд (type='poison'); null — яд придуман мастером. */
  poisonRuleId?: string | null;
  /** Тип урона яда; не задан — берётся из правила-яда. */
  damage_type_code?: string;
  /** Фактическая Сила (урон за тик) — размерное число; не задана — берётся шаблон яда. */
  strength?: DimensionalNumberValue;
  /** Фактическая периодичность; не задана — шаблон яда. */
  periodicity?: StatePeriodicity;
  /** Фактическое затухание; не задано — шаблон яда. */
  decay?: StateDecay;
}
