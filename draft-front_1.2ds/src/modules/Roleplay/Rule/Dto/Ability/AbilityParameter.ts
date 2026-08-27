import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Параметр способности (Дискуссия 2). Значение — размерная величина
 * (DimensionalNumberValue) или число; используется подстановкой `{code}`.
 * - purchase — значение выбирается при покупке, хранится на инстансе (build.abilities).
 * - activation — выбирается при активации; пока не используется (активации нет).
 */
export interface AbilityParameter {
  /** Ссылочный ключ, напр. 'x' — подстановка {x} в цене/дарах/описании. */
  code: string;
  label: string;
  /** Подпись параметра в редакторе способности. */
  description?: string;
  resolution: 'purchase' | 'activation';
  default: DimensionalNumberValue | number;
  /** Пределы опциональны. */
  min?: DimensionalNumberValue | number;
  max?: DimensionalNumberValue | number;
  /**
   * Связь с параметром другой способности: значение этого параметра не может отличаться от
   * значения связанного более чем на max_delta (напр. «Врождённая Стойкость X» не отличается
   * от «Врождённой Силы X» более чем на 3). Ограничение применяется к min/max в редакторе.
   */
  linked?: {
    ability_code: string;
    parameter_code: string;
    max_delta: number;
  };
}
