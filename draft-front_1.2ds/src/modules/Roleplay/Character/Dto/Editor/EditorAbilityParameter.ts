import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Ступень табличной цены параметра: значение (размерное) и его стоимость в ОС. */
export interface EditorParameterStep {
  value: DimensionalNumberValue;
  cost: number;
}

/** Параметр «X» покупки способности в модели редактора: диапазон и текущее значение. */
export interface EditorAbilityParameter {
  code: string;
  label: string;
  /** Текущее значение (из выбора персонажа или расы/дефолта); 0 = не взята. */
  value: DimensionalNumberValue;
  min: DimensionalNumberValue;
  /** Эффективный максимум: min(максимум спеки, потолок расы). */
  max: DimensionalNumberValue;
  /** Стоимость за единицу параметра (из os-зоны kind 'parameter'). */
  perUnit: number;
  /** Таблица цен по значению параметра (из os-зоны kind 'parameter_table'); ключи — toString() значений. */
  costs?: Record<string, number>;
  /** Доступные ступени табличной цены в порядке возрастания (для matrix-выбора). */
  steps: EditorParameterStep[];
  /** Потолок расы задан (имя способности показывает «до N»). */
  cappedByRace: boolean;
  /** Бесплатное автоматическое значение расы (докупка сверх него); 0 = нет. */
  freeValue: number;
  /** Табличная цена бесплатной ступени расы (parameter_table); ступени ниже неё недоступны, стоимость остальных инкрементальна. 0 = нет. */
  freeStepCost: number;
}
