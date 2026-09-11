import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface AbilityOverview {
  ruleCode: string;
  /** Уникальный ключ строки: правило + экземпляр домена (множественный навык). */
  instanceKey: string;
  name: string;
  /** Подпись экземпляра (семья оружия, язык); null у одиночной способности. */
  domainLabel: string | null;
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
  /** Длительность эффекта заклинания; у типа spell. Null у остальных. */
  spellDurationLabel: string | null;
  /** Мощь заклинания (размерное значение или параметр вроде x↑). Null у не-spell. */
  spellPowerLabel: string | null;
  /** Контроль заклинания. Null у не-spell. */
  spellControlLabel: string | null;
  href: string | null;
  isResolved: boolean;
}
