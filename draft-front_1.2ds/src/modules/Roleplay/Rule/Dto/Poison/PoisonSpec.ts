import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';

/**
 * Спека яда (type='poison'): шаблон отравления, которое навешивают предметы/способности
 * или мастер. Фактические параметры применения (Сила/Периодичность/Затухание) хранятся
 * на записи состояния персонажа; здесь — значения по умолчанию для показа и быстрой навески.
 */
export interface PoisonSpec {
  /** mdi-иконка яда (напр. 'mdi-skull-outline'). */
  icon_code?: string | null;
  /** Тип урона яда — обязателен (напр. 'poison-1', 'spirit-1'). */
  damage_type_code: string;
  /** Шаблон: Сила (урон за тик) — размерное число, если источник не задал свою. */
  default_strength?: DimensionalNumberValue;
  /** Шаблон: периодичность. */
  default_periodicity?: StatePeriodicity;
  /** Шаблон: затухание. */
  default_decay?: StateDecay;
}
