import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AbilityOverview {
  ruleId: string;
  name: string;
  level: number;
  /** Параметрическая способность (напр. «Врождённая Сила X»): уровень — заглушка, величину несёт параметр. */
  hasParameters: boolean;
  /** Тип способности из спеки правила (trait/feature/skill/action/process/spell); null, если правило не разрешено. */
  type: AbilityType | null;
  description: string;
  /** Признаки способности — id ключевых слов правила. */
  keywordIds: number[];
  /** Стоимость действия в ОД (компоненты action-points); у типа action. Null у остальных типов. */
  actionOdCost: DimensionalNumberValue | number | null;
  /** Сотворение заклинания — стоимость в ОД (компонент action-points); у типа spell. Null у остальных. */
  spellCastCost: DimensionalNumberValue | number | null;
  /** Сложность сотворения заклинания; у типа spell. Null у остальных. */
  spellDifficulty: DimensionalNumberValue | null;
  /** Длительность заклинания («Мгновенное», «Поддерживаемое (…»); у типа spell. Null у остальных. */
  spellDurationLabel: string | null;
  href: string | null;
  isResolved: boolean;
}
