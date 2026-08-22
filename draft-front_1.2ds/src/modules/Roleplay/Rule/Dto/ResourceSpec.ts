import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

/**
 * Условие изменения лимита авто-ресурса: вычисленное значение + источник (для попапа ресурса).
 * Значения складываются/вычитаются напрямую (без размерных переходов, как у характеристик).
 */
export interface ResourceLimitAdjustment {
  value: Formula;
  /** Ссылка на правило-источник (type='source'), которому атрибутируется вклад. */
  source_code: string;
}

/** Базовый лимит авто-ресурса: стартовое значение + условия его изменения. */
export interface ResourceLimit {
  /** Стартовое значение лимита: число (безразмерный ресурс) или размерное (размерный). */
  base: DimensionalNumberValue | number;
  /** Условия изменения лимита (у ОД: размер Ловкости/Восприятия + разница Сила−Вес). */
  adjustments: ResourceLimitAdjustment[];
}

/** Ресурс (type='resource') — определение ресурса персонажа. */
export interface ResourceSpec {
  is_dimensional: boolean;
  /**
   * Авто-добавление ресурса персонажу (сейчас — только ОД). Лимит считается из `limit`.
   * Ресурс рендерится всегда, даже при лимите 0 (персонаж не может действовать).
   */
  auto_add?: boolean;
  /** Базовый лимит ресурса (стартовое значение + условия). У не-авто ресурсов — base, adjustments пустые. */
  limit?: ResourceLimit;
}
