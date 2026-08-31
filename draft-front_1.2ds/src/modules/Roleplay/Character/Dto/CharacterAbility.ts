import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface CharacterAbility {
  ruleCode: string;
  level: number;
  /** Значения параметров покупки (resolution 'purchase') по кодам параметров способности. */
  parameters?: Record<string, DimensionalNumberValue | number>;
  /**
   * Зона покупки способности (код очков: 'os' | 'or' | 'ol'). Для способностей с несколькими
   * покупаемыми зонами фиксирует, из какой зоны списана стоимость (D111).
   */
  zone?: string;
  /**
   * Домен экземпляра множественного навыка (одна запись = один экземпляр; значение — имя из
   * словаря или свой текст). У не-multiple способностей отсутствует.
   */
  domain?: string;
  /** Код правила словаря домена (вид/язык); null — своё текстовое значение. */
  domainCode?: string | null;
  /**
   * Материализованная запись способности-дара (D100 / врождённое владение): уровень 1 из гранта
   * не списывает бюджет; апгрейд сверх gifted-уровня оплачивается разницей.
   */
  gifted?: boolean;
}
